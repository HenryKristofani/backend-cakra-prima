<?php

namespace App\Imports;

use App\Models\DebtGroup;
use App\Models\DebtItem;
use App\Models\DebtPayment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DebtGroupImport implements ToCollection
{
    protected DebtGroup $debtGroup;

    public function __construct(DebtGroup $debtGroup)
    {
        $this->debtGroup = $debtGroup;
    }

    public function collection(Collection $rows)
    {
        $newItems = [];
        $newPayments = [];
        $parsingMode = 'ITEMS';

        // 1. Gather all data first to ensure the file is valid
        foreach ($rows as $index => $row) {
            // Skip Title, Spacer, and Table Headers (Rows 1 to 3 => index 0 to 2)
            if ($index < 3) {
                continue;
            }

            $colA = isset($row[0]) ? trim((string) $row[0]) : '';
            $colB = isset($row[1]) ? trim((string) $row[1]) : '';
            $colC = isset($row[2]) ? trim((string) $row[2]) : '';
            $colD = isset($row[3]) ? preg_replace('/[^0-9.-]/', '', (string) $row[3]) : '';

            if (strtoupper($colA) === 'TOTAL HUTANG') {
                $parsingMode = 'PAYMENTS';
                continue;
            }

            if (strtoupper($colA) === 'SISA HUTANG') {
                break;
            }

            // Skip completely empty rows (spacers)
            if ($colA === '' && $colB === '' && $colC === '' && $colD === '') {
                continue;
            }

            if ($parsingMode === 'ITEMS') {
                if ($colB === '' || $colD === '') {
                    continue;
                }

                $no = $colA !== '' ? (int) $colA : null;
                $newItems[] = [
                    'debt_group_id' => $this->debtGroup->id,
                    'no'            => $no,
                    'description'   => $colB,
                    'trans_date'    => $this->parseDate($colC),
                    'amount'        => (float) $colD,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            } elseif ($parsingMode === 'PAYMENTS') {
                if ($colA === '' || $colD === '') {
                    continue;
                }

                $newPayments[] = [
                    'debt_group_id' => $this->debtGroup->id,
                    'description'   => $colA,
                    'payment_date'  => $this->parseDate($colC),
                    'amount'        => (float) $colD,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }
        }

        // 2. Perform Sync in Transaction
        DB::transaction(function () use ($newItems, $newPayments) {
            // Hapus data lama agar benar-benar sinkron (mirror) dengan Excel
            $this->debtGroup->items()->delete();
            $this->debtGroup->payments()->delete();

            // Masukkan data baru
            if (!empty($newItems)) {
                DebtItem::insert($newItems);
            }
            
            if (!empty($newPayments)) {
                DebtPayment::insert($newPayments);
            }

            $this->debtGroup->recalculate();
        });
    }

    private function parseDate(string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // Excel sometimes reads dates as numeric values (Excel serial date)
            if (is_numeric($dateString)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateString)->format('Y-m-d');
            }
            
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return null; // Ignore invalid dates
        }
    }
}
