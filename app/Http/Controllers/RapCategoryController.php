<?php

namespace App\Http\Controllers;

use App\Models\RapCategory;
use App\Models\Project;
use Illuminate\Http\Request;

class RapCategoryController extends Controller
{
    public function index(Project $project)
    {
        $categories = RapCategory::where('project_id', $project->id)
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->with([
                    'children.items.sourceRabItem:id,description,unit_price', 
                    'children.items.transactions',
                    'items.sourceRabItem:id,description,unit_price',
                    'items.transactions'
                ]);
            }, 'items.sourceRabItem:id,description,unit_price', 'items.transactions'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $potongan = \App\Models\RapSetting::resolvePotongan($project->id);

        $appendFields = function ($category) use (&$appendFields, $potongan) {
            if ($category->items) {
                $category->items = $category->items->map(function ($item) use ($potongan) {
                    $effectiveUnitPrice = (float) $item->unit_price * (1 - $potongan / 100);
                    $totalPrice         = (float) $item->volume * $effectiveUnitPrice;
                    $totalRealisasi     = (float) $item->transactions->sum('expense');
                    
                    $item->setAttribute('effective_unit_price', round($effectiveUnitPrice, 2));
                    $item->setAttribute('total_price', round($totalPrice, 2));
                    $item->setAttribute('total_realisasi', round($totalRealisasi, 2));
                    $item->setAttribute('selisih_laba_rugi', round($totalPrice - $totalRealisasi, 2));
                    $item->setAttribute('potongan_percentage', $potongan);
                    
                    return $item;
                });
            }
            if ($category->children) {
                $category->children = $category->children->map($appendFields);
            }
            return $category;
        };

        return $categories->map($appendFields);
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'code'       => 'nullable|string|max:10',
            'name'       => 'required|string',
            'parent_id'  => ['nullable', 'integer', 'exists:rap_categories,id'],
            'sort_order' => 'nullable|integer',
        ]);

        if (isset($validated['parent_id'])) {
            $parent = RapCategory::find($validated['parent_id']);
            if (!$parent || $parent->project_id !== $project->id) {
                return response()->json(['message' => 'Invalid parent_id for this project.'], 422);
            }
        }

        $validated['project_id'] = $project->id;

        $category = RapCategory::create($validated);
        return response()->json($category, 201);
    }

    public function show(RapCategory $rapCategory)
    {
        return $rapCategory->load('items');
    }

    public function update(Request $request, RapCategory $rapCategory)
    {
        $validated = $request->validate([
            'code'       => 'nullable|string|max:10',
            'name'       => 'sometimes|string',
            'parent_id'  => ['nullable', 'integer', 'exists:rap_categories,id'],
            'sort_order' => 'nullable|integer',
        ]);

        if (array_key_exists('parent_id', $validated)) {
            if ($validated['parent_id'] === $rapCategory->id) {
                return response()->json(['message' => 'parent_id cannot be the category itself.'], 422);
            }

            if ($validated['parent_id'] !== null) {
                $parent = RapCategory::find($validated['parent_id']);
                if (!$parent || $parent->project_id !== $rapCategory->project_id) {
                    return response()->json(['message' => 'Invalid parent_id for this project.'], 422);
                }

                if ($this->hasCircularParentReference($rapCategory, $parent)) {
                    return response()->json(['message' => 'Circular parent reference is not allowed.'], 422);
                }
            }
        }

        $rapCategory->update($validated);
        return $rapCategory;
    }

    public function destroy(RapCategory $rapCategory)
    {
        $rapCategory->delete();
        return response()->noContent();
    }

    private function hasCircularParentReference(RapCategory $category, RapCategory $newParent): bool
    {
        $current = $newParent;
        while ($current) {
            if ($current->id === $category->id) {
                return true;
            }
            $current = $current->parent;
        }
        return false;
    }
}
