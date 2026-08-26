<?php

namespace App\Imports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Illuminate\Support\Collection;

class LegacyTransactionImport implements ToCollection, WithCalculatedFormulas
{
    protected array $parsedRows = [];
    protected ?int $projectId;
    protected ?int $cashAccountId;
    protected ?int $rekAccountId;
    protected bool $isIsolated;

    public function __construct(?int $projectId, ?int $cashAccountId, ?int $rekAccountId, bool $isIsolated)
    {
        $this->projectId = $projectId;
        $this->cashAccountId = $cashAccountId;
        $this->rekAccountId = $rekAccountId;
        $this->isIsolated = $isIsolated;
    }

    public function collection(Collection $rows)
    {
        $isHeaderFound = false;

        foreach ($rows as $rowIndex => $row) {
            $rowNum = $rowIndex + 1; // 1-indexed for error reporting
            
            // Wait until we hit the real table header
            if (!$isHeaderFound) {
                $firstCell = strtoupper(trim((string) ($row[0] ?? '')));
                if ($firstCell === 'TANGGAL' || strpos($firstCell, 'TANGGAL') !== false) {
                    $isHeaderFound = true;
                }
                continue;
            }

            // Once header is found, parse data rows
            $rawDate = trim((string) ($row[0] ?? ''));
            $rawDesc = trim((string) ($row[1] ?? ''));
            
            // Skip empty rows completely
            if ($rawDate === '' && $rawDesc === '') {
                continue;
            }

            $errors = [];
            $data = [];
            $rawMethod = strtolower(trim((string) ($row[2] ?? '')));
            
            // ── Tanggal ──────────────────────────────────────────────────────
            if (empty($rawDate)) {
                $errors[] = 'Kolom Tanggal wajib diisi';
            } else {
                try {
                    if (is_numeric($rawDate)) {
                        $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $rawDate);
                        $data['date'] = $date->format('Y-m-d');
                    } else {
                        $data['date'] = Carbon::parse($rawDate)->format('Y-m-d');
                    }
                } catch (\Throwable) {
                    $errors[] = "Tanggal '{$rawDate}' tidak valid";
                }
            }

            // ── Deskripsi ─────────────────────────────────────────────────────
            if (empty($rawDesc)) {
                $errors[] = 'Kolom Keterangan wajib diisi';
            }
            // For legacy import, if description is exactly "SALDO AWAL", we just import it as is
            // Note: The user said we should not skip it, but import it as normal income
            if (strtoupper($rawDesc) === 'SALDO AWAL') {
                $data['description'] = 'Saldo Awal';
            } else {
                $data['description'] = $rawDesc;
            }

            // ── Metode Pembayaran & Account ───────────────────────────────────
            if ($rawMethod === 'cash') {
                $data['payment_method'] = 'cash';
                $data['account_id'] = $this->cashAccountId;
            } elseif ($rawMethod === 'rek') {
                $data['payment_method'] = 'rek';
                $data['account_id'] = $this->rekAccountId;
            } else {
                $errors[] = "Metode '{$rawMethod}' tidak valid (harus cash atau rek)";
                $data['payment_method'] = $rawMethod;
                $data['account_id'] = null;
            }

            // ── Pemasukan / Pengeluaran ───────────────────────────────────────
            $income  = (float) preg_replace('/[^0-9.]/', '', (string) ($row[4] ?? '0'));
            $expense = (float) preg_replace('/[^0-9.]/', '', (string) ($row[3] ?? '0'));
            
            if ($income <= 0 && $expense <= 0) {
                $errors[] = 'Pemasukan atau Pengeluaran harus lebih dari 0';
            }
            $data['income']  = $income;
            $data['expense'] = $expense;

            // ── Static values for this import format ──────────────────────────
            $data['project_id']   = $this->projectId;
            $data['_is_isolated'] = $this->isIsolated;
            
            // Since this format lacks these columns, set to null
            $data['company']     = null;
            $data['rap_item_id'] = null;

            $this->parsedRows[] = [
                'row'      => $rowNum,
                'data'     => $data,
                'errors'   => $errors,
                'is_valid' => empty($errors),
                'raw' => [
                    'tanggal'          => $rawDate,
                    'akun'             => $data['account_id'] ? ($rawMethod === 'cash' ? 'Akun Kas (Default)' : 'Akun Rek (Default)') : '-',
                    'project'          => 'Project Terpilih',
                    'item_rap'         => '-',
                    'deskripsi'        => $data['description'] ?? '',
                    'perusahaan_pihak' => '-',
                    'metode'           => $rawMethod,
                    'pemasukan'        => $income,
                    'pengeluaran'      => $expense,
                ],
            ];
        }
    }

    public function getParsedRows(): array
    {
        return $this->parsedRows;
    }
}
