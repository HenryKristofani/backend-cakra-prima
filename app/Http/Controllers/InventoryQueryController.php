<?php

namespace App\Http\Controllers;

use App\Models\StockBalance;
use App\Models\StockTransferLine;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryQueryController extends Controller
{
    /**
     * GET /inventory/items
     */
    public function getItems(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $all = $request->query('all'); // Jika true, ambil semua, jika tidak hanya yang aktif

        $query = \App\Models\Item::with('unit')->select('id', 'name', 'unit_id', 'sku', 'category', 'is_active');

        if (!$all) {
            $query->where('is_active', true);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $items = $query->orderBy('name')->get();
        return response()->json(['data' => $items]);
    }

    /**
     * GET /inventory/items/{id}
     */
    public function getItem($id): JsonResponse
    {
        $item = \App\Models\Item::with('unit')->select('id', 'name', 'unit_id')->findOrFail($id);
        return response()->json(['data' => $item]);
    }

    /**
     * GET /inventory/warehouses
     */
    public function getWarehouses(Request $request): JsonResponse
    {
        $type = $request->query('type');
        
        $query = Warehouse::with('project:id,name')->select('id', 'name', 'type', 'project_id', 'location', 'is_active');
        
        if ($type) {
            $query->where('type', $type);
        }
        
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    /**
     * 1, 2, 3: GET /inventory/stock-balances
     */
    public function stockBalances(Request $request): JsonResponse
    {
        $itemId = $request->query('item_id');
        $warehouseId = $request->query('warehouse_id');
        $showEmpty = filter_var($request->query('show_empty', false), FILTER_VALIDATE_BOOLEAN);

        // 2: GET /inventory/stock-balances?item_id=X (Breakdown per warehouse)
        if ($itemId && !$warehouseId) {
            $query = StockBalance::with('warehouse:id,name,type')
                ->where('item_id', $itemId)
                ->orderByDesc('quantity_on_hand');
                
            if (!$showEmpty) {
                $query->where('quantity_on_hand', '>', 0);
            }
            return response()->json(['data' => $query->get()]);
        }

        // 3: GET /inventory/stock-balances?warehouse_id=X (All items in one warehouse)
        if ($warehouseId && !$itemId) {
            $query = StockBalance::with('item:id,name,unit_id', 'item.unit')
                ->where('warehouse_id', $warehouseId)
                ->orderByDesc('quantity_on_hand');

            if (!$showEmpty) {
                $query->where('quantity_on_hand', '>', 0);
            }
            return response()->json(['data' => $query->get()]);
        }

        // 1: GET /inventory/stock-balances (All stocks aggregate)
        $query = StockBalance::selectRaw('item_id, SUM(quantity_on_hand) as total_qty, COUNT(DISTINCT warehouse_id) as location_count')
            ->with('item:id,name,unit_id', 'item.unit')
            ->groupBy('item_id');

        if (!$showEmpty) {
            $query->havingRaw('SUM(quantity_on_hand) > 0');
        }

        $results = $query->get();

        // Transform for cleaner JSON (flatten item relation)
        $transformed = $results->map(function ($row) {
            return [
                'item_id' => $row->item_id,
                'item_name' => $row->item->name ?? null,
                'item_unit' => $row->item->unit ? $row->item->unit->symbol : null,
                'total_qty' => (float) $row->total_qty,
                'location_count' => (int) $row->location_count,
            ];
        });

        return response()->json(['data' => $transformed]);
    }

    /**
     * 4: GET /inventory/stock-moves
     */
    public function stockMoves(Request $request): JsonResponse
    {
        $itemId = $request->query('item_id');
        $warehouseId = $request->query('warehouse_id');

        $query = StockTransferLine::select('stock_transfer_lines.*')
            ->join('stock_transfers', 'stock_transfer_lines.stock_transfer_id', '=', 'stock_transfers.id')
            ->with(['item:id,name,unit_id', 'item.unit', 'stockTransfer.sourceWarehouse:id,name,type', 'stockTransfer.destinationWarehouse:id,name,type'])
            ->orderByDesc('stock_transfers.created_at')
            ->orderByDesc('stock_transfers.id');

        if ($itemId) {
            $query->where('stock_transfer_lines.item_id', $itemId);
        }

        if ($warehouseId) {
            $query->where(function ($q) use ($warehouseId) {
                $q->where('stock_transfers.source_warehouse_id', $warehouseId)
                  ->orWhere('stock_transfers.destination_warehouse_id', $warehouseId);
            });
        }

        $results = $query->get();

        $transformed = $results->map(function ($line) {
            $transfer = $line->stockTransfer;
            return [
                'date' => $transfer->created_at->toIso8601String(),
                'type' => $transfer->type->value,
                'reference_number' => $transfer->reference_number,
                'source_warehouse' => $transfer->sourceWarehouse ? $transfer->sourceWarehouse->name : null,
                'destination_warehouse' => $transfer->destinationWarehouse ? $transfer->destinationWarehouse->name : null,
                'item_name' => $line->item->name ?? null,
                'item_unit' => $line->item->unit ? $line->item->unit->symbol : null,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
            ];
        });

        return response()->json(['data' => $transformed]);
    }

    /**
     * 5: GET /projects/{id}/stock-report
     */
    public function projectStockReport($projectId): JsonResponse
    {
        $warehouse = Warehouse::where('project_id', $projectId)->first();
        if (!$warehouse) {
            return response()->json(['message' => 'Project tidak memiliki gudang yang terdaftar.'], 404);
        }
        $warehouseId = $warehouse->id;

        // 1. Get all items currently in this warehouse or ever transferred here
        $itemsQuery = DB::table('stock_transfer_lines')
            ->join('stock_transfers', 'stock_transfer_lines.stock_transfer_id', '=', 'stock_transfers.id')
            ->join('items', 'stock_transfer_lines.item_id', '=', 'items.id')
            ->where(function ($q) use ($warehouseId) {
                $q->where('stock_transfers.source_warehouse_id', $warehouseId)
                  ->orWhere('stock_transfers.destination_warehouse_id', $warehouseId);
            })
            ->select('items.id', 'items.name', 'items.unit_id')
            ->distinct()
            ->get();

        $unitIds = $itemsQuery->pluck('unit_id')->filter()->unique();
        $units = DB::table('units')->whereIn('id', $unitIds)->get()->keyBy('id');

        $itemsQuery = $itemsQuery->keyBy('id');

        // 2. Get total received
        $received = DB::table('stock_transfer_lines')
            ->join('stock_transfers', 'stock_transfer_lines.stock_transfer_id', '=', 'stock_transfers.id')
            ->where('stock_transfers.destination_warehouse_id', $warehouseId)
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total_received')
            ->pluck('total_received', 'item_id');

        // 3. Get total used & total value
        $used = DB::table('stock_usages')
            ->join('stock_transfer_lines', 'stock_usages.stock_transfer_line_id', '=', 'stock_transfer_lines.id')
            ->where('stock_usages.warehouse_id', $warehouseId)
            ->groupBy('stock_usages.item_id')
            ->selectRaw('stock_usages.item_id, SUM(stock_usages.quantity) as total_used, SUM(stock_usages.quantity * stock_transfer_lines.unit_price) as total_value_used')
            ->get()
            ->keyBy('item_id');

        // Combine
        $report = [];
        foreach ($itemsQuery as $itemId => $item) {
            $totalReceived = (float) ($received->get($itemId) ?? 0);
            
            $usedRow = $used->get($itemId);
            $totalUsed = (float) ($usedRow->total_used ?? 0);
            $totalValueUsed = (float) ($usedRow->total_value_used ?? 0);
            
            $remaining = $totalReceived - $totalUsed;

            $report[] = [
                'item_id' => $itemId,
                'item_name' => $item->name,
                'item_unit' => isset($units[$item->unit_id]) ? $units[$item->unit_id]->symbol : null,
                'total_received' => $totalReceived,
                'total_used' => $totalUsed,
                'remaining' => $remaining,
                'total_value_used' => $totalValueUsed,
            ];
        }

        return response()->json(['data' => $report]);
    }
}
