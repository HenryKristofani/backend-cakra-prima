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
            'status' => 'required|in:aktif,dibatalkan,dikurangi',
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
            'status' => 'sometimes|in:aktif,dibatalkan,dikurangi',
        ]);

        $item->update($validated);
        return $item->load('progressReports');
    }

    public function destroy(RabCategory $rabCategory, RabItem $item)
    {
        $item->delete();
        return response()->noContent();
    }

    public function bulkStore(Request $request, RabCategory $rabCategory)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $rabCategory) {
            $validated = $request->validate([
                'items' => 'required|array',
                'items.*.description' => 'required|string',
                'items.*.volume' => 'required|numeric',
                'items.*.unit' => 'required|string',
                'items.*.unit_price' => 'required|numeric',
                'items.*.sort_order' => 'nullable|integer',
                'items.*.status' => 'required|in:aktif,dibatalkan,dikurangi',
            ]);

            $createdItems = collect();
            foreach ($validated['items'] as $itemData) {
                $itemData['category_id'] = $rabCategory->id;
                $createdItems->push(RabItem::create($itemData));
            }

            return response()->json($this->appendCalculatedFields($createdItems, $rabCategory), 201);
        });
    }

    public function bulkUpdate(Request $request, RabCategory $rabCategory)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $rabCategory) {
            $validated = $request->validate([
                'items' => 'required|array',
                'items.*.id' => 'required|exists:rab_items,id',
                'items.*.description' => 'required|string',
                'items.*.volume' => 'required|numeric',
                'items.*.unit' => 'required|string',
                'items.*.unit_price' => 'required|numeric',
                'items.*.sort_order' => 'nullable|integer',
                'items.*.status' => 'required|in:aktif,dibatalkan,dikurangi',
            ]);

            $updatedItems = collect();
            foreach ($validated['items'] as $itemData) {
                $item = RabItem::where('category_id', $rabCategory->id)->findOrFail($itemData['id']);
                $item->update($itemData);
                $updatedItems->push($item);
            }

            return response()->json($this->appendCalculatedFields($updatedItems, $rabCategory), 200);
        });
    }

    private function appendCalculatedFields($items, RabCategory $rabCategory)
    {
        $items->each(fn ($item) => $item->load('progressReports'));

        $totalActive = (float) $rabCategory->project->rabItems()
            ->whereIn('status', ['aktif', 'dikurangi'])
            ->get()
            ->sum(fn ($i) => (float) $i->total_price);

        return $items->map(function ($item) use ($totalActive) {
            $totalPrice = (float) $item->total_price;
            $latest = (float) $item->latest_progress_percentage;
            $bobot = 0.0;
            $totalPercentage = 0.0;

            if ($totalActive > 0) {
                $bobot = round(($totalPrice / $totalActive) * 100.0, 2);
            }

            if ($item->status === 'aktif' && $totalActive > 0) {
                $totalPercentage = round($bobot * ($latest / 100.0), 2);
            }

            $array = $item->toArray();
            $array['total_price'] = $totalPrice;
            $array['bobot_percentage'] = $item->status === 'dikurangi' ? 0.0 : $bobot;
            $array['latest_progress_percentage'] = $latest;
            $array['total_percentage'] = $item->status === 'dikurangi' ? 0.0 : $totalPercentage;
            
            return $array;
        });
    }
}
