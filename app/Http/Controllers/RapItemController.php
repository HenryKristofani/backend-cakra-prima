<?php

namespace App\Http\Controllers;

use App\Models\RapCategory;
use App\Models\RapItem;
use App\Models\RapSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RapItemController extends Controller
{
    public function index(RapCategory $rapCategory)
    {
        $pajak = RapSetting::resolvePajak((int) $rapCategory->project_id);

        return $rapCategory->items()->with('sourceRabItem:id,description,unit_price')->get()->map(fn ($item) => $this->appendCalculatedFields($item, $pajak));
    }

    public function store(Request $request, RapCategory $rapCategory)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'volume'      => 'required|numeric|min:0',
            'unit'        => 'required|string',
            'unit_price'  => 'required|numeric|min:0',
            'sort_order'  => 'nullable|integer',
        ]);

        $validated['category_id'] = $rapCategory->id;
        $item = RapItem::create($validated);
        $item->load(['category', 'sourceRabItem:id,description,unit_price']);

        return response()->json(
            $this->appendCalculatedFields($item, RapSetting::resolvePotongan((int) $rapCategory->project_id)),
            201
        );
    }

    public function show(RapCategory $rapCategory, RapItem $item)
    {
        $item->load(['category', 'sourceRabItem:id,description,unit_price']);
        return $this->appendCalculatedFields($item, RapSetting::resolvePotongan((int) $rapCategory->project_id));
    }

    public function update(Request $request, RapCategory $rapCategory, RapItem $item)
    {
        $validated = $request->validate([
            'description' => 'sometimes|string',
            'volume'      => 'sometimes|numeric|min:0',
            'unit'        => 'sometimes|string',
            'unit_price'  => 'sometimes|numeric|min:0',
            'sort_order'  => 'nullable|integer',
        ]);

        $item->update($validated);
        $item->load(['category', 'sourceRabItem:id,description,unit_price']);

        return $this->appendCalculatedFields($item, RapSetting::resolvePotongan((int) $rapCategory->project_id));
    }

    public function destroy(RapCategory $rapCategory, RapItem $item)
    {
        $item->delete();
        return response()->noContent();
    }

    public function bulkStore(Request $request, RapCategory $rapCategory)
    {
        return DB::transaction(function () use ($request, $rapCategory) {
            $validated = $request->validate([
                'items'               => 'required|array',
                'items.*.description' => 'required|string',
                'items.*.volume'      => 'required|numeric|min:0',
                'items.*.unit'        => 'required|string',
                'items.*.unit_price'  => 'required|numeric|min:0',
                'items.*.sort_order'  => 'nullable|integer',
            ]);

            $pajak = RapSetting::resolvePajak((int) $rapCategory->project_id);

            $createdItems = collect();
            foreach ($validated['items'] as $itemData) {
                $item = $rapCategory->items()->create($itemData);
                $item->setRelation('category', $rapCategory);
                $item->load('sourceRabItem:id,description,unit_price');
                $createdItems->push($this->appendCalculatedFields($item, $pajak));
            }

            return response()->json($createdItems, 201);
        });
    }

    public function bulkUpdate(Request $request, RapCategory $rapCategory)
    {
        return DB::transaction(function () use ($request, $rapCategory) {
            $validated = $request->validate([
                'items'               => 'required|array',
                'items.*.id'          => 'required|exists:rap_items,id',
                'items.*.description' => 'required|string',
                'items.*.volume'      => 'required|numeric|min:0',
                'items.*.unit'        => 'required|string',
                'items.*.unit_price'  => 'required|numeric|min:0',
                'items.*.sort_order'  => 'nullable|integer',
            ]);

            $pajak = RapSetting::resolvePajak((int) $rapCategory->project_id);

            $updatedItems = collect();
            foreach ($validated['items'] as $itemData) {
                $item = RapItem::where('category_id', $rapCategory->id)->findOrFail($itemData['id']);
                $item->update($itemData);
                $item->setRelation('category', $rapCategory);
                $item->load('sourceRabItem:id,description,unit_price');
                $updatedItems->push($this->appendCalculatedFields($item, $pajak));
            }

            return response()->json($updatedItems, 200);
        });
    }

    // ─── Helper ─────────────────────────────────────────────────────────────────

    private function appendCalculatedFields(RapItem $item, float $pajak): array
    {
        $arr = $item->toArray();
        $arr['effective_unit_price']   = $item->effective_unit_price;
        $arr['total_price']            = round($item->total_price, 2);
        $arr['total_realisasi']        = round($item->total_realisasi, 2);
        $arr['selisih_laba_rugi']      = round($item->selisih_laba_rugi, 2);
        $arr['pajak_percentage']       = $pajak;

        return $arr;
    }
}
