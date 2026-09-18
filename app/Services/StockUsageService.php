<?php

namespace App\Services;

use App\Models\ProjectKasTransaction;
use App\Models\StockUsage;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockUsageService
{
    /**
     * Post a StockUsage record to Kas.
     *
     * Routing logic mirrors TransactionController (mutually exclusive):
     *   - is_isolated_cash = true  → project_kas_transactions ONLY (no account_id)
     *   - is_isolated_cash = false → transactions (Kas Buku Besar) ONLY (requires account_id)
     *
     * Note: kas_transaction_id in stock_usages is a plain integer audit-trail reference
     * (FK was dropped in migration 2026_09_18_041937). The referenced table is determined
     * by the project's is_isolated_cash flag at runtime.
     *
     * @param int   $stockUsageId
     * @param int   $userId
     * @param array $paymentData  Keys: payment_method, date, and optionally account_id
     * @return Model  Either a Transaction or ProjectKasTransaction instance
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function postToKas(int $stockUsageId, int $userId, array $paymentData): Model
    {
        return DB::transaction(function () use ($stockUsageId, $userId, $paymentData) {
            $usage = StockUsage::with(['item', 'warehouse.project', 'stockTransferLine'])
                ->lockForUpdate()
                ->findOrFail($stockUsageId);

            // GUARD: Idempotent check
            if ($usage->posted_to_kas) {
                throw new \DomainException("Pemakaian ini sudah pernah diposting ke Kas.");
            }

            // GUARD: Warehouse must be a project warehouse with an associated project
            if ($usage->warehouse->type->value !== 'project') {
                throw new \InvalidArgumentException("Hanya pemakaian di gudang project yang dapat diposting ke Kas.");
            }

            if (!$usage->warehouse->project_id) {
                throw new \InvalidArgumentException("Gudang project ini tidak memiliki ID Project yang terasosiasi.");
            }

            $project = $usage->warehouse->project;

            // Calculate total expense
            $totalExpense = $usage->quantity * $usage->stockTransferLine->unit_price;

            // Generate description
            $unitName = $usage->item->unit?->name ?? 'pcs';
            $description = "Pemakaian {$usage->item->name} " . (float)$usage->quantity . " {$unitName}";
            if (!empty($usage->usage_note)) {
                $description .= " - {$usage->usage_note}";
            }

            $date = $paymentData['date'] ?? $usage->used_at->format('Y-m-d');

            if ($project->is_isolated_cash) {
                // Isolated project: write to project_kas_transactions ONLY (no account_id column)
                $trx = ProjectKasTransaction::create([
                    'project_id'     => $project->id,
                    'user_id'        => $userId,
                    'date'           => $date,
                    'description'    => $description,
                    'payment_method' => $paymentData['payment_method'],
                    'expense'        => $totalExpense,
                    'income'         => 0,
                ]);
            } else {
                // Non-isolated project: write to transactions (Kas Buku Besar)
                if (empty($paymentData['account_id'])) {
                    throw new \InvalidArgumentException("Account ID wajib diisi untuk project yang tidak menggunakan Kas Mandiri.");
                }

                $trx = Transaction::create([
                    'account_id'     => $paymentData['account_id'],
                    'project_id'     => $project->id,
                    'user_id'        => $userId,
                    'date'           => $date,
                    'description'    => $description,
                    'payment_method' => $paymentData['payment_method'],
                    'expense'        => $totalExpense,
                    'income'         => 0,
                ]);
            }

            // Update StockUsage status; kas_transaction_id is a plain integer now (FK dropped)
            $usage->update([
                'posted_to_kas'      => true,
                'kas_transaction_id' => $trx->id,
            ]);

            return $trx;
        });
    }
}
