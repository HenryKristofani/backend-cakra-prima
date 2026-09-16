<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockTransferRequest;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    protected StockTransferService $service;

    public function __construct(StockTransferService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');

        $query = StockTransfer::with([
            'lines.item',
            'sourceWarehouse:id,name,type',
            'destinationWarehouse:id,name,type'
        ])->orderBy('created_at', 'desc');

        if ($warehouseId) {
            $query->where(function ($q) use ($warehouseId) {
                $q->where('source_warehouse_id', $warehouseId)
                  ->orWhere('destination_warehouse_id', $warehouseId);
            });
        }

        $transfers = $query->paginate(15);
        
        return response()->json($transfers);
    }

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = auth()->id() ?? 1; // Fallback to 1 if testing without auth, adjust based on project auth mechanism

        if ($data['type'] === 'in') {
            $transfer = $this->service->createReceipt($data, $userId);
            
            return response()->json([
                'message' => 'Stock receipt created successfully',
                'data' => $transfer
            ], 201);
        } elseif ($data['type'] === 'transfer') {
            $transfer = $this->service->createTransfer($data, $userId);

            return response()->json([
                'message' => 'Stock transfer created successfully',
                'data' => $transfer
            ], 201);
        } elseif ($data['type'] === 'usage') {
            try {
                $transfer = $this->service->createUsage($data, $userId);

                return response()->json([
                    'message' => 'Stock usage created successfully',
                    'data' => $transfer
                ], 201);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json(['message' => 'Unsupported transfer type'], 400);
    }

    public function getUsages(\Illuminate\Http\Request $request): JsonResponse
    {
        $query = \App\Models\StockUsage::with(['item', 'warehouse', 'stockTransferLine.stockTransfer']);

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->query('warehouse_id'));
        }

        if ($request->has('posted_to_kas')) {
            $isPosted = filter_var($request->query('posted_to_kas'), FILTER_VALIDATE_BOOLEAN);
            $query->where('posted_to_kas', $isPosted);
        }

        return response()->json([
            'data' => $query->get()
        ]);
    }

    public function postToKas(\App\Http\Requests\PostToKasRequest $request, $id, \App\Services\StockUsageService $usageService): JsonResponse
    {
        try {
            $transaction = $usageService->postToKas(
                $id, 
                auth()->id() ?? 1, 
                $request->validated()
            );

            return response()->json([
                'message' => 'Stock usage successfully posted to Kas',
                'data' => $transaction
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Stock usage record not found'
            ], 404);
        }
    }
}
