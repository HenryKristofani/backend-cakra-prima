<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;

class WarehouseController extends Controller
{
    /**
     * POST /inventory/warehouses
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create($request->validated());
        // Load project relation if exists
        $warehouse->load('project');
        
        return response()->json([
            'message' => 'Gudang berhasil ditambahkan',
            'data' => $warehouse
        ], 201);
    }

    /**
     * GET /inventory/warehouses/{id}
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load('project');
        return response()->json(['data' => $warehouse]);
    }

    /**
     * PUT /inventory/warehouses/{id}
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update($request->validated());
        $warehouse->load('project');
        
        return response()->json([
            'message' => 'Gudang berhasil diupdate',
            'data' => $warehouse
        ]);
    }
}
