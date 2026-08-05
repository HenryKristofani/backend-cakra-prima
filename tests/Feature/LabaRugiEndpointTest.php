<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RapCategory;
use App\Models\RapItem;
use App\Models\RapSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LabaRugiEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;
    private RapCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user    = User::factory()->create();
        $this->project = Project::create(['name' => 'Test Project', 'status' => 'active']);
        $this->category = RapCategory::create([
            'project_id' => $this->project->id,
            'name'       => 'Pekerjaan Sipil',
            'sort_order' => 0,
        ]);
    }

    public function test_laba_rugi_returns_correct_structure(): void
    {
        RapItem::create([
            'category_id' => $this->category->id,
            'description' => 'Galian Tanah',
            'volume'      => 10,
            'unit'        => 'm3',
            'unit_price'  => 50_000,
            'sort_order'  => 0,
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/laba-rugi")
            ->assertOk()
            ->assertJsonStructure([
                'items' => [['id', 'description', 'total_price', 'total_realisasi', 'selisih_laba_rugi', 'status_label']],
                'summary' => ['total_rencana', 'total_realisasi', 'total_selisih', 'status_label', 'pajak_percentage'],
            ]);
    }

    public function test_laba_rugi_with_mixed_untung_rugi(): void
    {
        // Item 1 — untung: rencana 500000, realisasi 400000 → selisih +100000
        $item1 = RapItem::create([
            'category_id' => $this->category->id,
            'description' => 'Pekerjaan A',
            'volume'      => 10,
            'unit'        => 'm2',
            'unit_price'  => 50_000,
            'sort_order'  => 0,
        ]);

        // Item 2 — rugi: rencana 300000, realisasi 400000 → selisih -100000
        $item2 = RapItem::create([
            'category_id' => $this->category->id,
            'description' => 'Pekerjaan B',
            'volume'      => 3,
            'unit'        => 'm',
            'unit_price'  => 100_000,
            'sort_order'  => 1,
        ]);

        // Realisasi transaksi
        DB::table('transactions')->insert([
            ['rap_item_id' => $item1->id, 'date' => now()->toDateString(), 'company' => 'V1', 'description' => 'Bayar A', 'payment_method' => 'cash', 'expense' => 400_000, 'income' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['rap_item_id' => $item2->id, 'date' => now()->toDateString(), 'company' => 'V2', 'description' => 'Bayar B', 'payment_method' => 'cash', 'expense' => 400_000, 'income' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/laba-rugi")
            ->assertOk();

        $data = $response->json();

        // Find item1 and item2 in response
        $responseItem1 = collect($data['items'])->firstWhere('id', $item1->id);
        $responseItem2 = collect($data['items'])->firstWhere('id', $item2->id);

        $this->assertEquals(500_000.0, $responseItem1['total_price']);
        $this->assertEquals(400_000.0, $responseItem1['total_realisasi']);
        $this->assertEquals(100_000.0, $responseItem1['selisih_laba_rugi']);
        $this->assertEquals('untung',  $responseItem1['status_label']);

        $this->assertEquals(300_000.0, $responseItem2['total_price']);
        $this->assertEquals(400_000.0, $responseItem2['total_realisasi']);
        $this->assertEquals(-100_000.0,$responseItem2['selisih_laba_rugi']);
        $this->assertEquals('rugi',    $responseItem2['status_label']);

        // Summary: rencana=800000, realisasi=800000, selisih=0 (impas)
        $this->assertEquals(800_000.0, $data['summary']['total_rencana']);
        $this->assertEquals(800_000.0, $data['summary']['total_realisasi']);
        $this->assertEquals(0.0,       $data['summary']['total_selisih']);
        $this->assertEquals('impas',   $data['summary']['status_label']);
    }

    public function test_laba_rugi_with_no_transactions_returns_zero_realisasi(): void
    {
        RapItem::create([
            'category_id' => $this->category->id,
            'description' => 'Item Tanpa Realisasi',
            'volume'      => 5,
            'unit'        => 'ls',
            'unit_price'  => 100_000,
            'sort_order'  => 0,
        ]);

        $data = $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/laba-rugi")
            ->assertOk()
            ->json();

        $item = $data['items'][0];
        $this->assertEquals(500_000.0, $item['total_price']);
        $this->assertEquals(0.0,       $item['total_realisasi'], 'Tanpa transaksi total_realisasi harus 0');
        $this->assertEquals(500_000.0, $item['selisih_laba_rugi']);
        $this->assertEquals('untung',  $item['status_label']);
    }

    public function test_laba_rugi_applies_pajak_from_setting(): void
    {
        // Project-specific pajak 10%
        RapSetting::create(['project_id' => $this->project->id, 'pajak_percentage' => 10.0]);

        RapItem::create([
            'category_id' => $this->category->id,
            'description' => 'Item Dengan Pajak',
            'volume'      => 5,
            'unit'        => 'm2',
            'unit_price'  => 100_000,
            'sort_order'  => 0,
        ]);

        $data = $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/laba-rugi")
            ->assertOk()
            ->json();

        // With 10% pajak: effective = 110000, total = 5 * 110000 = 550000
        $this->assertEquals(550_000.0, $data['items'][0]['total_price']);
        $this->assertEquals(10.0,      $data['summary']['pajak_percentage']);
    }

    public function test_laba_rugi_returns_empty_items_for_project_without_rap(): void
    {
        $data = $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/laba-rugi")
            ->assertOk()
            ->json();

        $this->assertIsArray($data['items']);
        $this->assertCount(0, $data['items']);
        $this->assertEquals(0.0, $data['summary']['total_rencana']);
    }
}
