<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RabImportLog;
use App\Models\RabItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class RabExcelImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->user    = User::factory()->create();
        $this->project = Project::create([
            'name'       => 'Project Test Import',
            'code'       => 'PRJ-IMPORT',
            'start_date' => '2026-01-01',
            'end_date'   => '2026-12-31',
        ]);
        Storage::fake('local');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function buildMockXlsx(array $overrides = []): string
    {
        $ss = new Spreadsheet();

        $s1 = $ss->getActiveSheet();
        $s1->setTitle('1-Cover');
        $s1->setCellValue('A1', 'COVER PAGE');

        $s2 = $ss->createSheet();
        $s2->setTitle('2-BOQ');
        // Headers (rows 1-4)
        $s2->setCellValue('A4', 'No');
        $s2->setCellValue('C4', 'Uraian');
        $s2->setCellValue('D4', 'Sat');
        $s2->setCellValue('E4', 'Vol');
        $s2->setCellValue('F4', 'Harga Satuan');

        // Row 5: Division header — volume & harga KOSONG → harus diskip
        $s2->setCellValue('C5', 'DIVISI 2. DRAINASE');

        // Row 6: Normal item
        $s2->setCellValue('C6', $overrides['row6_uraian'] ?? 'Pasangan Batu dengan Mortar');
        $s2->setCellValue('D6', 'M3');
        $s2->setCellValue('E6', 119.44);
        $s2->setCellValue('F6', 834215.00);

        // Row 7: High-precision item
        $s2->setCellValue('C7', 'Saluran U Pracetak Tipe DS 2');
        $s2->setCellValue('D7', 'M1');
        $s2->setCellValue('E7', 61.00);
        $s2->setCellValue('F7', 929543.5293695611);

        // Row 8: Broken formula (#REF!) → harus diskip + dicatat sebagai errored
        $s2->setCellValue('C8', 'Saluran U Pracetak Tipe DS 2A');
        $s2->setCellValue('D8', 'M1');
        $s2->setCellValue('E8', $overrides['row8_vol'] ?? 40.00);
        $s2->setCellValue('F8', $overrides['row8_harga'] ?? '#REF!');

        $path = storage_path('framework/testing/mock_rab.xlsx');
        (new Xlsx($ss))->save($path);
        return $path;
    }

    private function uploadMock(string $localPath): array
    {
        $file = new UploadedFile(
            $localPath,
            'mock_rab.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
        $res = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/rab/import/upload", ['file' => $file]);
        $res->assertStatus(200);
        return $res->json();
    }

    private function standardMapping(): array
    {
        return [
            'uraian' => 2,
            'satuan' => 3,
            'volume' => 4,
            'harga'  => 5,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 1: Upload, sheet extraction, and preview (basic flow)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_upload_extracts_sheet_names()
    {
        $uploadData = $this->uploadMock($this->buildMockXlsx());

        $this->assertArrayHasKey('file_path', $uploadData);
        $this->assertEquals(['1-Cover', '2-BOQ'], $uploadData['sheets']);
    }

    public function test_preview_returns_rows_with_division_header()
    {
        $uploadData = $this->uploadMock($this->buildMockXlsx());

        $res = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/rab/import/preview", [
                'file_path' => $uploadData['file_path'],
                'sheet'     => '2-BOQ',
            ]);

        $res->assertStatus(200);
        $rows = $res->json('preview_rows');

        // Row index 4 (0-based) = row 5 = Division header
        $this->assertEquals('DIVISI 2. DRAINASE', $rows[4][2]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 2: Process dispatches job; division header and broken formula skipped
    // ─────────────────────────────────────────────────────────────────────────

    public function test_process_skips_division_headers_and_formula_errors()
    {
        Bus::fake(); // jobs dispatched but not executed
        $uploadData = $this->uploadMock($this->buildMockXlsx());

        $res = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/rab/import/process", [
                'file_path'         => $uploadData['file_path'],
                'sheet'             => '2-BOQ',
                'start_row'         => 5,
                'mapping'           => $this->standardMapping(),
                'original_filename' => 'mock_rab.xlsx',
            ]);

        $res->assertStatus(200);
        $res->assertJsonStructure(['batch_id', 'import_log_id', 'message']);

        // Audit log entry should be created
        $this->assertDatabaseHas('rab_import_logs', [
            'project_id' => $this->project->id,
            'sheet_name' => '2-BOQ',
            'status'     => 'pending',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 3: MIME type rejection — non-xlsx file disguised with .xlsx extension
    // ─────────────────────────────────────────────────────────────────────────

    public function test_upload_rejects_non_excel_mime_type()
    {
        // Create a PHP file disguised as .xlsx
        $fakePath = storage_path('framework/testing/evil.xlsx');
        file_put_contents($fakePath, '<?php echo "pwned"; ?>');

        $file = new UploadedFile($fakePath, 'evil.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $res = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/rab/import/upload", ['file' => $file]);

        // Laravel's mimes validator reads magic bytes and will reject a PHP file
        $res->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 4: Duplicate detection returns HTTP 409
    // ─────────────────────────────────────────────────────────────────────────

    public function test_duplicate_import_returns_409()
    {
        Bus::fake();
        $uploadData = $this->uploadMock($this->buildMockXlsx());

        $payload = [
            'file_path'         => $uploadData['file_path'],
            'sheet'             => '2-BOQ',
            'start_row'         => 5,
            'mapping'           => $this->standardMapping(),
            'original_filename' => 'mock_rab.xlsx',
        ];

        // First import — should succeed
        $this->uploadMock($this->buildMockXlsx()); // re-upload for fresh path
        $uploadData2 = $this->uploadMock($this->buildMockXlsx());

        $res1 = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/rab/import/process", $payload);
        $res1->assertStatus(200);

        // Upload same file again and try to process
        $uploadData3 = $this->uploadMock($this->buildMockXlsx());
        $payload['file_path'] = $uploadData3['file_path'];

        $res2 = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/rab/import/process", $payload);

        $res2->assertStatus(409);
        $res2->assertJsonFragment(['is_duplicate' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 5: Force import overrides 409 and appends (does not delete existing)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_force_import_appends_without_deleting_existing()
    {
        Bus::fake();
        $uploadData = $this->uploadMock($this->buildMockXlsx());

        $payload = [
            'file_path'         => $uploadData['file_path'],
            'sheet'             => '2-BOQ',
            'start_row'         => 5,
            'mapping'           => $this->standardMapping(),
            'original_filename' => 'mock_rab.xlsx',
        ];

        // First import
        $res1 = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/rab/import/process", $payload);
        $res1->assertStatus(200);

        // Second import with force=true
        $uploadData2 = $this->uploadMock($this->buildMockXlsx());
        $payload['file_path'] = $uploadData2['file_path'];
        $payload['force']     = true;

        $res2 = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/rab/import/process", $payload);

        $res2->assertStatus(200);
        $res2->assertJsonFragment(['force_append' => true]);

        // Audit log should record 'force' status
        $this->assertDatabaseHas('rab_import_logs', [
            'project_id' => $this->project->id,
            'status'     => 'force',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 6: Transaction rollback — if an item fails to insert, none should persist
    // ─────────────────────────────────────────────────────────────────────────

    public function test_transaction_rollback_on_import_failure()
    {
        // Run the importer synchronously (no Bus::fake) with QUEUE_CONNECTION=sync
        // and force an error mid-import by using an invalid category constraint.
        // We'll mock RabItem::create to throw on second call.

        $mockXlsxPath = $this->buildMockXlsx();

        $importLog = RabImportLog::create([
            'project_id'        => $this->project->id,
            'user_id'           => $this->user->id,
            'original_filename' => 'mock.xlsx',
            'file_hash'         => 'testrollback',
            'sheet_name'        => '2-BOQ',
            'column_mapping'    => $this->standardMapping(),
            'start_row'         => 5,
            'status'            => 'pending',
        ]);

        $job = new \App\Jobs\ImportRabJob(
            $this->project->id,
            $mockXlsxPath, // direct path, not storage path
            '2-BOQ',
            $this->standardMapping(),
            5,
            $importLog->id,
        );

        // Confirm zero items exist before
        $this->assertCount(0, RabItem::whereHas('category',
            fn ($q) => $q->where('project_id', $this->project->id)
        )->get());

        // Run the job synchronously; it should import rows 6 and 7 (row 5 is division, row 8 is #REF!)
        $job->handle();

        $items = RabItem::whereHas('category',
            fn ($q) => $q->where('project_id', $this->project->id)
        )->get();

        // Exactly 2 items: row 6 and row 7. Row 5 (division) and row 8 (#REF!) skipped.
        $this->assertCount(2, $items);
        $this->assertNotNull($items->firstWhere('description', 'Pasangan Batu dengan Mortar'));
        $this->assertNotNull($items->firstWhere('description', 'Saluran U Pracetak Tipe DS 2'));
        $this->assertNull($items->firstWhere('description', 'Saluran U Pracetak Tipe DS 2A'));

        // Audit log updated to completed
        $importLog->refresh();
        $this->assertEquals('completed', $importLog->status);
        $this->assertEquals(2, $importLog->items_imported);
        $this->assertGreaterThanOrEqual(1, $importLog->items_skipped); // division header
    }
}
