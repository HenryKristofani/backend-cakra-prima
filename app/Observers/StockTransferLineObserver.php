<?php

namespace App\Observers;

use App\Models\StockTransferLine;
use App\Models\StockBalance;

class StockTransferLineObserver
{
    /**
     * Handle the StockTransferLine "created" event.
     */
    public function created(StockTransferLine $stockTransferLine): void
    {
        $transfer = $stockTransferLine->stockTransfer;

        // If it's an incoming receipt or transfer, we add to the destination warehouse
        if ($transfer->type->value === 'in' || $transfer->type->value === 'transfer') {
            if ($transfer->destination_warehouse_id) {
                $balance = StockBalance::firstOrCreate(
                    [
                        'warehouse_id' => $transfer->destination_warehouse_id,
                        'item_id' => $stockTransferLine->item_id,
                    ],
                    ['quantity_on_hand' => 0]
                );

                $balance->increment('quantity_on_hand', $stockTransferLine->quantity);
                
                // Manually touch updated_at since timestamps are false in StockBalance model
                $balance->updated_at = now();
                $balance->save();
            }
        }

        // If it's a transfer out or usage, we deduct from the source warehouse
        if ($transfer->type->value === 'transfer' || $transfer->type->value === 'usage') {
            if ($transfer->source_warehouse_id) {
                $balance = StockBalance::firstOrCreate(
                    [
                        'warehouse_id' => $transfer->source_warehouse_id,
                        'item_id' => $stockTransferLine->item_id,
                    ],
                    ['quantity_on_hand' => 0]
                );

                $balance->decrement('quantity_on_hand', $stockTransferLine->quantity);
                
                // Manually touch updated_at
                $balance->updated_at = now();
                $balance->save();
            }
        }
    }
}
