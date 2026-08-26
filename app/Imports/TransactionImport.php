<?php

namespace App\Imports;

use App\Models\Account;
use App\Models\Project;
use App\Models\RapItem;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Illuminate\Support\Collection;

class TransactionImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    protected array $parsedRows = [];

    public function collection(Collection $rows)
    {
        // Pre-load lookup tables for efficiency
        $accounts = Account::pluck('id', 'name')->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);
        $projects  = Project::pluck('id', 'name')->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);
        $projectIsolated = Project::pluck('is_isolated_cash', 'id');
        $rapItems  = RapItem::pluck('id', 'description')->mapWithKeys(fn($id, $desc) => [strtolower(trim($desc)) => $id]);

        foreach ($rows as $rowIndex => $row) {
            $rowNum = $rowIndex + 2; // 1-indexed, plus heading row
            $errors = [];
            $data   = [];

            // ── Tanggal ──────────────────────────────────────────────────────
            $rawDate = trim((string) ($row['tanggal'] ?? ''));
            if (empty($rawDate)) {
                $errors[] = 'Kolom Tanggal wajib diisi';
            } else {
                try {
                    // Support various date formats exported by Excel
                    if (is_numeric($rawDate)) {
                        // Excel serial date number
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
            $desc = trim((string) ($row['deskripsi'] ?? ''));
            if (empty($desc)) {
                $errors[] = 'Kolom Deskripsi wajib diisi';
            }
            $data['description'] = $desc;

            // ── Metode Pembayaran ─────────────────────────────────────────────
            $metode = strtolower(trim((string) ($row['metode'] ?? '')));
            if (!in_array($metode, ['cash', 'rek'])) {
                $errors[] = "Kolom Metode harus 'cash' atau 'rek', ditemukan: '{$metode}'";
            }
            $data['payment_method'] = $metode;

            // ── Pemasukan / Pengeluaran ───────────────────────────────────────
            $income  = (float) preg_replace('/[^0-9.]/', '', (string) ($row['pemasukan']  ?? '0'));
            $expense = (float) preg_replace('/[^0-9.]/', '', (string) ($row['pengeluaran'] ?? '0'));
            if ($income <= 0 && $expense <= 0) {
                $errors[] = 'Pemasukan atau Pengeluaran harus lebih dari 0';
            }
            $data['income']  = $income;
            $data['expense'] = $expense;

            // ── Perusahaan/Pihak (optional) ───────────────────────────────────
            $data['company'] = trim((string) ($row['perusahaanpihak'] ?? '')) ?: null;

            // ── Akun (optional, but must be valid if provided) ────────────────
            $rawAkun = trim((string) ($row['akun'] ?? ''));
            if (!empty($rawAkun)) {
                $accountId = $accounts[strtolower($rawAkun)] ?? null;
                if (!$accountId) {
                    $errors[] = "Akun '{$rawAkun}' tidak ditemukan di database";
                }
                $data['account_id'] = $accountId;
            } else {
                $data['account_id'] = null;
            }

            // ── Project (optional, but must be valid if provided) ─────────────
            $rawProject = trim((string) ($row['project'] ?? ''));
            $isIsolated = false;
            if (!empty($rawProject)) {
                $projectId = $projects[strtolower($rawProject)] ?? null;
                if (!$projectId) {
                    $errors[] = "Project '{$rawProject}' tidak ditemukan di database";
                }
                $data['project_id'] = $projectId;
                if ($projectId && ($projectIsolated[$projectId] ?? false)) {
                    $isIsolated = true;
                }
            } else {
                $data['project_id'] = null;
            }
            $data['_is_isolated'] = $isIsolated;

            // ── Item RAP (optional, must be valid if provided) ────────────────
            $rawRapItem = trim((string) ($row['item_rap'] ?? ''));
            if (!empty($rawRapItem)) {
                $rapItemId = $rapItems[strtolower($rawRapItem)] ?? null;
                if (!$rapItemId) {
                    $errors[] = "Item RAP '{$rawRapItem}' tidak ditemukan di database";
                }
                $data['rap_item_id'] = $rapItemId;
            } else {
                $data['rap_item_id'] = null;
            }

            $this->parsedRows[] = [
                'row'     => $rowNum,
                'data'    => $data,
                'errors'  => $errors,
                'is_valid'=> empty($errors),
                // Include raw values for display in preview
                'raw' => [
                    'tanggal'           => $row['tanggal'] ?? '',
                    'akun'              => $rawAkun,
                    'project'           => $rawProject,
                    'item_rap'          => $rawRapItem,
                    'deskripsi'         => $desc,
                    'perusahaan_pihak'  => $data['company'],
                    'metode'            => $metode,
                    'pemasukan'         => $income,
                    'pengeluaran'       => $expense,
                ],
            ];
        }
    }

    public function getParsedRows(): array
    {
        return $this->parsedRows;
    }
}
