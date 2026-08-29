<?php

namespace App\Imports;

use App\Models\RabCategory;
use App\Models\RabItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * CHUNKING NOTE:
 * WithChunkReading (chunk size 500) causes PhpSpreadsheet to read the spreadsheet
 * in batches of 500 rows to limit RAM usage. Each chunk calls collection() sequentially
 * in the SAME PHP process — this is NOT distributed across multiple jobs.
 * The wrapping DB::transaction() in ImportRabJob therefore covers all chunks correctly.
 */
class RabImport implements ToCollection, WithCalculatedFormulas, WithMultipleSheets, WithChunkReading
{
    protected int $projectId;
    protected string $sheetName;
    protected array $mapping;
    protected int $startRow;

    // Stats for audit log
    protected int $countImported = 0;
    protected int $countSkipped  = 0;
    protected int $countErrored  = 0;

    // Running row index across chunks (chunks reset $index if not tracked here)
    protected int $globalRowIndex = 0;

    // Default category — created once on first chunk
    protected ?int $defaultCategoryId = null;

    public function __construct(int $projectId, string $sheetName, array $mapping, int $startRow)
    {
        $this->projectId = $projectId;
        $this->sheetName = $sheetName;
        $this->mapping   = $mapping;
        $this->startRow  = $startRow;
    }

    public function sheets(): array
    {
        return [$this->sheetName => $this];
    }

    public function collection(Collection $rows): void
    {
        // Ensure default category exists (idempotent across chunks)
        if ($this->defaultCategoryId === null) {
            $category = RabCategory::firstOrCreate([
                'project_id' => $this->projectId,
                'name'       => 'Imported Items (' . now()->format('Y-m-d H:i') . ')',
            ]);
            $this->defaultCategoryId = $category->id;
        }

        foreach ($rows as $row) {
            $this->globalRowIndex++;

            // Skip rows before start_row
            if ($this->globalRowIndex < $this->startRow) {
                continue;
            }

            $uraian    = $row[$this->mapping['uraian']] ?? null;
            $volumeRaw = $row[$this->mapping['volume']] ?? null;
            $satuan    = $row[$this->mapping['satuan']] ?? null;
            $hargaRaw  = $row[$this->mapping['harga']] ?? null;

            // Skip completely empty rows
            if (empty(trim((string) $uraian))) {
                $this->countSkipped++;
                continue;
            }

            // Broken formula references (#REF!, #VALUE!, etc.)
            if ($this->isExcelError($volumeRaw) || $this->isExcelError($hargaRaw)) {
                Log::warning("Import RAB (Formula Error) row {$this->globalRowIndex}: '{$uraian}' has broken link.");
                $this->countErrored++;
                continue;
            }

            // Division/header rows — only import if BOTH volume AND harga are numeric
            if (!is_numeric($volumeRaw) || !is_numeric($hargaRaw)) {
                Log::info("Import RAB (Division Header) row {$this->globalRowIndex}: '{$uraian}' skipped (no numeric volume/harga).");
                $this->countSkipped++;
                continue;
            }

            // Insert — unit_price stored as string to preserve DECIMAL(24,10) precision
            RabItem::create([
                'category_id' => $this->defaultCategoryId,
                'description' => trim((string) $uraian),
                'volume'      => (float) $volumeRaw,
                'unit'        => trim((string) $satuan),
                'unit_price'  => (string) $hargaRaw,
                'status'      => 'aktif',
            ]);

            $this->countImported++;
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Return import statistics for the audit log.
     */
    public function getStats(): array
    {
        return [
            'imported' => $this->countImported,
            'skipped'  => $this->countSkipped,
            'errored'  => $this->countErrored,
        ];
    }

    private function isExcelError($value): bool
    {
        $errors = ['#N/A', '#VALUE!', '#REF!', '#DIV/0!', '#NUM!', '#NAME?', '#NULL!'];
        return is_string($value) && in_array(strtoupper(trim($value)), $errors);
    }
}
