<?php

namespace App\Services;

use App\Models\StockTransfer;
use App\Enums\StockTransferType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockTransferService
{
    /**
     * Generate reference number securely with lock.
     * Must be called inside a DB::transaction.
     */
    private function generateReferenceNumber(): string
    {
        $year = now()->year;
        
        $lastNumberStr = StockTransfer::whereYear('created_at', $year)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('reference_number');

        $sequence = 1;
        if ($lastNumberStr) {
            // Expected format: TRF-2026-0001
            $parts = explode('-', $lastNumberStr);
            if (count($parts) === 3) {
                $sequence = (int) $parts[2] + 1;
            }
        }

        return 'TRF-' . $year . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create an incoming stock receipt.
     */
    public function createReceipt(array $data, int $userId): StockTransfer
    {
        return DB::transaction(function () use ($data, $userId) {
            $referenceNumber = $this->generateReferenceNumber();

            $transfer = StockTransfer::create([
                'reference_number' => $referenceNumber,
                'type' => StockTransferType::In,
                'source_type' => $data['source_type'],
                'source_warehouse_id' => null, // Receipt doesn't come from another warehouse in our system
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($data['items'] as $itemData) {
                $quantity = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];
                $totalPrice = $quantity * $unitPrice;

                // Creating the line will trigger StockTransferLineObserver 
                // which automatically updates the stock_balances
                $transfer->lines()->create([
                    'item_id' => $itemData['item_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);
            }

            return $transfer->load('lines');
        });
    }

    /**
     * Create an outgoing stock transfer to another warehouse.
     */
    public function createTransfer(array $data, int $userId): StockTransfer
    {
        return DB::transaction(function () use ($data, $userId) {
            $sourceWarehouseId = $data['source_warehouse_id'];
            
            // Extract item IDs requested
            $itemIds = array_column($data['items'], 'item_id');
            
            // Lock and load balances for the requested items in the source warehouse
            $balances = \App\Models\StockBalance::where('warehouse_id', $sourceWarehouseId)
                ->whereIn('item_id', $itemIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('item_id');
                
            $insufficientItems = [];
            
            // Validate each requested item
            foreach ($data['items'] as $itemData) {
                $itemId = $itemData['item_id'];
                $requestedQty = (float) $itemData['quantity'];
                
                // Determine available quantity (0 if no balance record exists)
                $availableQty = 0;
                if ($balances->has($itemId)) {
                    $availableQty = (float) $balances->get($itemId)->quantity_on_hand;
                }
                
                if ($availableQty < $requestedQty) {
                    $itemName = \App\Models\Item::find($itemId)->name ?? "Item ID {$itemId}";
                    $insufficientItems[] = "{$itemName} (diminta: {$requestedQty}, stok sedia: {$availableQty})";
                }
            }
            
            // If any item is insufficient, throw exception
            if (!empty($insufficientItems)) {
                throw new \App\Exceptions\InsufficientStockException($insufficientItems);
            }

            $referenceNumber = $this->generateReferenceNumber();

            $transfer = StockTransfer::create([
                'reference_number' => $referenceNumber,
                'type' => StockTransferType::Transfer,
                'source_type' => null,
                'source_warehouse_id' => $sourceWarehouseId,
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($data['items'] as $itemData) {
                $quantity = (float) $itemData['quantity'];
                $itemId = $itemData['item_id'];

                // Auto-lookup the most recent unit_price for this item in the source warehouse.
                // We look at stock_transfer_lines where the item arrived at (destination = source warehouse),
                // ordered by most recent first.
                $latestLine = \Illuminate\Support\Facades\DB::table('stock_transfer_lines')
                    ->join('stock_transfers', 'stock_transfer_lines.stock_transfer_id', '=', 'stock_transfers.id')
                    ->where('stock_transfer_lines.item_id', $itemId)
                    ->where('stock_transfers.destination_warehouse_id', $sourceWarehouseId)
                    ->orderByDesc('stock_transfers.created_at')
                    ->orderByDesc('stock_transfers.id')
                    ->select('stock_transfer_lines.unit_price')
                    ->first();

                if (!$latestLine) {
                    $itemName = \App\Models\Item::find($itemId)->name ?? "Item ID {$itemId}";
                    throw new \InvalidArgumentException(
                        "Tidak dapat menemukan histori harga untuk '{$itemName}' di gudang asal. " .
                        "Pastikan item ini pernah diterima di gudang tersebut sebelum ditransfer."
                    );
                }

                $unitPrice = (float) $latestLine->unit_price;
                $totalPrice = $quantity * $unitPrice;

                // Creating the line will trigger StockTransferLineObserver
                // which automatically deducts from source and adds to destination
                $transfer->lines()->create([
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);
            }

            return $transfer->load('lines');
        });
    }

    /**
     * Create an outgoing stock usage from a project warehouse.
     */
    public function createUsage(array $data, int $userId): StockTransfer
    {
        return DB::transaction(function () use ($data, $userId) {
            $warehouseId = $data['warehouse_id'];
            
            // Validate warehouse type is 'project'
            $warehouse = \App\Models\Warehouse::find($warehouseId);
            if (!$warehouse || $warehouse->type->value !== 'project') {
                throw new \InvalidArgumentException("Pemakaian barang (usage) hanya dapat dilakukan dari gudang tipe project.");
            }
            
            // Extract item IDs requested
            $itemIds = array_column($data['items'], 'item_id');
            
            // Lock and load balances for the requested items in the source warehouse
            $balances = \App\Models\StockBalance::where('warehouse_id', $warehouseId)
                ->whereIn('item_id', $itemIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('item_id');
                
            $insufficientItems = [];
            
            // Validate each requested item
            foreach ($data['items'] as $itemData) {
                $itemId = $itemData['item_id'];
                $requestedQty = (float) $itemData['quantity'];
                
                // Determine available quantity (0 if no balance record exists)
                $availableQty = 0;
                if ($balances->has($itemId)) {
                    $availableQty = (float) $balances->get($itemId)->quantity_on_hand;
                }
                
                if ($availableQty < $requestedQty) {
                    $itemName = \App\Models\Item::find($itemId)->name ?? "Item ID {$itemId}";
                    $insufficientItems[] = "{$itemName} (diminta: {$requestedQty}, stok sedia: {$availableQty})";
                }
            }
            
            // If any item is insufficient, throw exception
            if (!empty($insufficientItems)) {
                throw new \App\Exceptions\InsufficientStockException($insufficientItems);
            }

            $referenceNumber = $this->generateReferenceNumber();

            $transfer = StockTransfer::create([
                'reference_number' => $referenceNumber,
                'type' => StockTransferType::Usage,
                'source_type' => null,
                'source_warehouse_id' => $warehouseId,
                'destination_warehouse_id' => null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($data['items'] as $itemData) {
                $quantity = (float) $itemData['quantity'];
                $itemId = $itemData['item_id'];
                $usageNote = $itemData['usage_note'] ?? null;

                // Auto-lookup the most recent unit_price for this item in the project warehouse.
                $latestLine = \Illuminate\Support\Facades\DB::table('stock_transfer_lines')
                    ->join('stock_transfers', 'stock_transfer_lines.stock_transfer_id', '=', 'stock_transfers.id')
                    ->where('stock_transfer_lines.item_id', $itemId)
                    ->where('stock_transfers.destination_warehouse_id', $warehouseId)
                    ->orderByDesc('stock_transfers.created_at')
                    ->orderByDesc('stock_transfers.id')
                    ->select('stock_transfer_lines.unit_price')
                    ->first();

                if (!$latestLine) {
                    $itemName = \App\Models\Item::find($itemId)->name ?? "Item ID {$itemId}";
                    throw new \InvalidArgumentException(
                        "Tidak dapat menemukan histori harga untuk '{$itemName}' di gudang project ini. " .
                        "Pastikan item ini pernah masuk ke gudang tersebut sebelum dipakai."
                    );
                }

                $unitPrice = (float) $latestLine->unit_price;
                $totalPrice = $quantity * $unitPrice;

                // Create the line (triggers Observer to deduct from source balance)
                $line = $transfer->lines()->create([
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);
                
                // Explicitly create stock_usage row for this line
                $line->stockUsage()->create([
                    'warehouse_id' => $warehouseId,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'usage_note' => $usageNote,
                    'posted_to_kas' => false,
                    'used_at' => now(),
                ]);
            }

            return $transfer->load('lines.stockUsage');
        });
    }
}
