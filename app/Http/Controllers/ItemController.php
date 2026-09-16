<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ItemController extends Controller
{
    /**
     * Store a newly created item in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:items,sku',
            'unit_id' => 'required|exists:units,id',
            'category' => 'nullable|string|max:100',
            'is_active' => 'boolean'
        ]);

        $item = Item::create([
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'unit_id' => $validated['unit_id'],
            'category' => $validated['category'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $item->load('unit');

        return response()->json([
            'message' => 'Item berhasil dibuat',
            'data' => $item
        ], 201);
    }

    /**
     * Update the specified item in storage.
     */
    public function update(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:items,sku,' . $item->id,
            'unit_id' => 'sometimes|required|exists:units,id',
            'category' => 'nullable|string|max:100',
            'is_active' => 'boolean'
        ]);

        $item->update($validated);

        $item->load('unit');

        return response()->json([
            'message' => 'Item berhasil diperbarui',
            'data' => $item
        ]);
    }
}
