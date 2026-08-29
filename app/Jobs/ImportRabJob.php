<?php

namespace App\Jobs;

use App\Imports\RabImport;
use App\Models\RabImportLog;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * ARCHITECTURE NOTE — Chunking model:
 *
 * RabImport implements WithChunkReading (NOT ShouldQueue).
 * This means Excel chunking is SYNCHRONOUS within this single job:
 * PhpSpreadsheet reads rows in batches of 500 to limit RAM usage,
 * but all batches execute in the same PHP process, same DB connection.
 *
 * The DB::transaction() wrapping below therefore correctly covers
 * ALL chunks — if chunk N fails, all rows from chunks 1..(N-1) roll back.
 *
 * TRADE-OFF: A long-running transaction (can be 30-120s for a 14MB file)
 * holds row-level locks on newly inserted rows for its entire duration.
 * For this use case this is acceptable because:
 * (a) imports are typically run during low-traffic periods,
 * (b) locks are only on freshly inserted rows in a newly created category,
 *     not on existing data that other users might be reading/editing.
 * If contention becomes a problem in the future, consider chunking the
 * import into multiple separate committed transactions with a compensating
 * cleanup pass on failure (saga pattern).
 */
class ImportRabJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200; // 20 minutes max

    protected int $importLogId;
    protected string $filePath;
    protected string $sheetName;
    protected array $mapping;
    protected int $startRow;
    protected int $projectId;

    public function __construct(
        int $projectId,
        string $filePath,
        string $sheetName,
        array $mapping,
        int $startRow,
        int $importLogId
    ) {
        $this->projectId   = $projectId;
        $this->filePath    = $filePath;
        $this->sheetName   = $sheetName;
        $this->mapping     = $mapping;
        $this->startRow    = $startRow;
        $this->importLogId = $importLogId;
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            $this->cleanup();
            return;
        }

        $log = RabImportLog::find($this->importLogId);
        if ($log) {
            $log->update(['status' => 'processing', 'started_at' => now()]);
        }

        // All chunks run synchronously in one DB transaction.
        // See architecture note at the top of this class.
        $importer = new RabImport($this->projectId, $this->sheetName, $this->mapping, $this->startRow);

        DB::transaction(function () use ($importer) {
            Excel::import($importer, $this->filePath);
        });

        $stats = $importer->getStats();

        if ($log) {
            $log->update([
                'status'         => 'completed',
                'items_imported' => $stats['imported'],
                'items_skipped'  => $stats['skipped'],
                'items_errored'  => $stats['errored'],
                'finished_at'    => now(),
            ]);
        }

        $this->cleanup();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ImportRabJob failed for project {$this->projectId}: " . $exception->getMessage(), [
            'file'  => $this->filePath,
            'sheet' => $this->sheetName,
        ]);

        $log = RabImportLog::find($this->importLogId);
        if ($log) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at'   => now(),
            ]);
        }

        $this->cleanup();
    }

    private function cleanup(): void
    {
        if ($this->filePath && Storage::exists($this->filePath)) {
            Storage::delete($this->filePath);
        }
    }
}
