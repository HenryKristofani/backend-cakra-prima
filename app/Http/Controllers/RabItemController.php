<?php

namespace App\Http\Controllers;

use App\Models\RabCategory;
use App\Models\RabItem;
use Illuminate\Http\Request;

class RabItemController extends Controller
{
    public function index(RabCategory $rabCategory)
    {
        return $rabCategory->items()->with('progressReports')->get();
    }

    public function store(Request $request, RabCategory $rabCategory)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'volume' => 'required|numeric',
            'unit' => 'required|string',
            'unit_price' => 'required|numeric',
            'sort_order' => 'nullable|integer',
            'status' => 'required|in:aktif,dibatalkan',
        ]);

        $validated['category_id'] = $rabCategory->id;

        $item = RabItem::create($validated);
        return response()->json($item->load('progressReports'), 201);
    }

    public function show(RabCategory $rabCategory, RabItem $item)
    {
        return $item->load('progressReports');
    }

    public function update(Request $request, RabCategory $rabCategory, RabItem $item)
    {
        $validated = $request->validate([
            'description' => 'sometimes|string',
            'volume' => 'sometimes|numeric',
            'unit' => 'sometimes|string',
            'unit_price' => 'sometimes|numeric',
            'sort_order' => 'nullable|integer',
            'status' => 'sometimes|in:aktif,dibatalkan',
        ]);

        $item->update($validated);
        return $item->load('progressReports');
    }

    public function destroy(RabCategory $rabCategory, RabItem $item)
    {
        $item->delete();
        return response()->noContent();
    }
}
