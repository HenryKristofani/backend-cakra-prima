<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RabImportLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Jobs\ImportRabJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class RabImportController extends Controller
{
    private const ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-excel',                                           // .xls
        'application/octet-stream',                                           // binary fallback
    ];

    /**
     * Step 1: Upload file, validate MIME dari magic bytes, ekstrak sheet names.
     *
     * Keamanan:
     *  - mimes:xlsx,xls → Symfony MimeGuesser membaca magic bytes, BUKAN hanya ekstensi
     *  - max:20480 → limit 20MB ditegakkan di backend PHP layer
     *  - File disimpan di storage/app/temp-imports (NON-publik, tidak bisa diakses via URL)
     *  - Nama file diacak 40 karakter untuk mencegah enumerasi
     *  - Verifikasi tambahan via mime_content_type() (defence-in-depth)
     *  - File dihapus oleh ImportRabJob::cleanup() setelah job selesai/gagal
     */
    public function upload(Request $request, Project $project)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        $file       = $request->file('file');
        $randomName = Str::random(40) . '.xlsx';
        $path       = $file->storeAs('temp-imports', $randomName);
        $fullPath   = Storage::path($path);

        // Defence-in-depth: re-verify binary MIME after storage
        $detectedMime = mime_content_type($fullPath);
        if (!in_array($detectedMime, self::ALLOWED_MIME_TYPES)) {
            Storage::delete($path);
            return response()->json([
                'message' => "Tipe file tidak diizinkan. Terdeteksi: {$detectedMime}"
            ], 422);
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
            $sheets      = $spreadsheet->getSheetNames();
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return response()->json([
                'file_path'         => $path,
                'original_filename' => $file->getClientOriginalName(),
                'sheets'            => $sheets,
            ]);
        } catch (\Exception $e) {
            Storage::delete($path);
            return response()->json(['message' => 'Gagal membaca file Excel: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Step 2: Preview 30 baris pertama dari sheet yang dipilih untuk mapping kolom.
     */
    public function preview(Request $request, Project $project)
    {
        $request->validate([
            'file_path' => ['required', 'string', function ($attr, $value, $fail) {
                if (!str_starts_with($value, 'temp-imports/')) {
                    $fail('Path file tidak valid.');
                }
            }],
            'sheet' => 'required|string|max:255',
        ]);

        $fullPath = Storage::path($request->file_path);

        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'File tidak ditemukan atau sudah kedaluwarsa.'], 404);
        }

        try {
            $reader = IOFactory::createReaderForFile($fullPath);
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([$request->sheet]);

            $spreadsheet = $reader->load($fullPath);
            $worksheet   = $spreadsheet->getSheetByName($request->sheet);

            $rows       = [];
            $maxRows    = 30;
            $currentRow = 0;

            foreach ($worksheet->getRowIterator() as $row) {
                if ($currentRow >= $maxRows) break;
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }
                $rows[] = $rowData;
                $currentRow++;
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return response()->json(['preview_rows' => $rows]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membaca sheet: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Step 3: Dispatch import job dengan mapping kolom yang dipilih user.
     *
     * DUPLICATE DETECTION:
     *  - Hash MD5 dari konten file + project_id + sheet_name disimpan di CACHE (24 jam) DAN
     *    dicek dari tabel rab_import_logs (permanen).
     *  - Jika duplikat terdeteksi, kembalikan HTTP 409 dengan is_duplicate=true.
     *  - User dapat memaksakan import dengan force=true (APPEND, bukan REPLACE — data lama tidak dihapus).
     *
     * FORCE IMPORT BEHAVIOR:
     *  "Import Paksa" adalah APPEND — item baru ditambahkan ke kategori "Imported Items" baru,
     *  data yang sudah ada di project TIDAK dihapus/diganti. Status log dicatat sebagai 'force'.
     */
    public function process(Request $request, Project $project)
    {
        $request->validate([
            'file_path' => ['required', 'string', function ($attr, $value, $fail) {
                if (!str_starts_with($value, 'temp-imports/')) {
                    $fail('Path file tidak valid.');
                }
            }],
            'sheet'          => 'required|string|max:255',
            'mapping'        => 'required|array',
            'mapping.uraian' => 'required|integer|min:0',
            'mapping.volume' => 'required|integer|min:0',
            'mapping.satuan' => 'required|integer|min:0',
            'mapping.harga'  => 'required|integer|min:0',
            'start_row'      => 'required|integer|min:1',
            'original_filename' => 'nullable|string|max:255',
            'force'          => 'boolean',
        ]);

        $fullPath = Storage::path($request->file_path);

        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'File tidak ditemukan atau sudah kedaluwarsa.'], 404);
        }

        $fileHash = md5_file($fullPath);
        $isForce  = $request->boolean('force', false);

        // ── Duplicate Detection ────────────────────────────────────────────────
        $cacheKey    = "rab_import_hash:{$project->id}:{$request->sheet}:{$fileHash}";
        $priorLog    = RabImportLog::where('project_id', $project->id)
            ->where('file_hash', $fileHash)
            ->where('sheet_name', $request->sheet)
            ->whereIn('status', ['completed', 'processing', 'force'])
            ->first();

        if (($priorLog || Cache::has($cacheKey)) && !$isForce) {
            return response()->json([
                'message'      => 'File ini (sheet yang sama) sepertinya sudah pernah diimport ke proyek ini. '
                    . 'Kirim ulang dengan `force: true` untuk APPEND item baru (data lama tidak dihapus).',
                'is_duplicate' => true,
                'prior_import' => $priorLog ? [
                    'imported_at'    => $priorLog->finished_at?->toIso8601String(),
                    'items_imported' => $priorLog->items_imported,
                    'status'         => $priorLog->status,
                ] : null,
            ], 409);
        }

        // ── Create Audit Log Entry (status=pending, updated by the job) ────────
        $importLog = RabImportLog::create([
            'project_id'        => $project->id,
            'user_id'           => $request->user()->id,
            'original_filename' => $request->input('original_filename', basename($request->file_path)),
            'file_hash'         => $fileHash,
            'sheet_name'        => $request->sheet,
            'column_mapping'    => $request->mapping,
            'start_row'         => $request->start_row,
            'status'            => $isForce ? 'force' : 'pending',
        ]);

        Cache::put($cacheKey, true, now()->addHours(24));

        $batch = Bus::batch([
            new ImportRabJob(
                $project->id,
                $request->file_path,
                $request->sheet,
                $request->mapping,
                $request->start_row,
                $importLog->id, // Audit log ID instead of userId
            )
        ])->name('Import RAB ' . $project->name)
          ->allowFailures(false)
          ->then(function () use ($importLog) {
              $importLog->update(['batch_id' => null]); // already updated by job
          })
          ->dispatch();

        // Store batch_id in audit log
        $importLog->update(['batch_id' => $batch->id]);

        return response()->json([
            'batch_id'        => $batch->id,
            'import_log_id'   => $importLog->id,
            'message'         => 'Proses import berjalan di background. "Import Paksa" = APPEND (data lama tidak dihapus).',
            'force_append'    => $isForce,
        ]);
    }

    /**
     * Cek status batch import untuk polling frontend.
     * Juga mengembalikan data audit log untuk transparansi.
     */
    public function status(Project $project, string $batchId)
    {
        $batch = Bus::findBatch($batchId);

        if (!$batch) {
            return response()->json(['message' => 'Batch tidak ditemukan'], 404);
        }

        $log = RabImportLog::where('batch_id', $batchId)->first();

        return response()->json([
            'id'             => $batch->id,
            'total_jobs'     => $batch->totalJobs,
            'pending_jobs'   => $batch->pendingJobs,
            'processed_jobs' => $batch->processedJobs(),
            'progress'       => $batch->progress(),
            'finished'       => $batch->finished(),
            'cancelled'      => $batch->cancelled(),
            'has_failures'   => $batch->hasFailures(),
            'failure_detail' => $batch->hasFailures()
                ? 'Import gagal dan semua data di-rollback. Tidak ada partial import. Cek log server untuk detail.'
                : null,
            'audit_log' => $log ? [
                'items_imported' => $log->items_imported,
                'items_skipped'  => $log->items_skipped,
                'items_errored'  => $log->items_errored,
                'started_at'     => $log->started_at?->toIso8601String(),
                'finished_at'    => $log->finished_at?->toIso8601String(),
                'status'         => $log->status,
            ] : null,
        ]);
    }

    /**
     * Riwayat semua import untuk project ini (untuk halaman audit/history).
     */
    public function history(Project $project)
    {
        $logs = RabImportLog::where('project_id', $project->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($logs);
    }
}
