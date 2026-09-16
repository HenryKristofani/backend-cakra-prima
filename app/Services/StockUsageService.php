<?php

namespace App\Services;

use App\Models\StockUsage;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class StockUsageService
{
    /**
     * Post a StockUsage record to Kas (Transaction).
     *
     * @param int $stockUsageId
     * @param int $userId
     * @param array $paymentData Data containing payment_method and account_id
     * @return Transaction
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function postToKas(int $stockUsageId, int $userId, array $paymentData): Transaction
    {
        return DB::transaction(function () use ($stockUsageId, $userId, $paymentData) {
            $usage = StockUsage::with(['item', 'warehouse', 'stockTransferLine'])
                ->lockForUpdate() // Prevent race condition if double posted
                ->findOrFail($stockUsageId);

            // GUARD: Idempotent check
            if ($usage->posted_to_kas) {
                throw new \DomainException("Pemakaian ini sudah pernah diposting ke Kas.");
            }

            // GUARD: Warehouse must be project and must have a project_id
            if ($usage->warehouse->type->value !== 'project') {
                throw new \InvalidArgumentException("Hanya pemakaian di gudang project yang dapat diposting ke Kas.");
            }

            if (!$usage->warehouse->project_id) {
                throw new \InvalidArgumentException("Gudang project ini tidak memiliki ID Project yang terasosiasi.");
            }

            // Calculate total expense
            $totalExpense = $usage->quantity * $usage->stockTransferLine->unit_price;

            // Generate description
            $description = "Pemakaian {$usage->item->name} " . (float)$usage->quantity . " {$usage->item->unit}";
            if (!empty($usage->usage_note)) {
                $description .= " - {$usage->usage_note}";
            }

            // Create Transaction record
            $transaction = Transaction::create([
                'account_id' => $paymentData['account_id'],
                'project_id' => $usage->warehouse->project_id,
                'user_id' => $userId,
                'date' => $usage->used_at->format('Y-m-d'), // Use used_at date per user request
                'description' => $description,
                'payment_method' => $paymentData['payment_method'],
                'expense' => $totalExpense,
                'income' => 0,
            ]);

            // Update StockUsage status
            $usage->update([
                'posted_to_kas' => true,
                'kas_transaction_id' => $transaction->id,
            ]);

            return $transaction;
        });
    }
}
