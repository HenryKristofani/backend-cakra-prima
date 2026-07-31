<?php

namespace App\Http\Controllers;

use App\Models\RabCategory;
use App\Models\Project;
use Illuminate\Http\Request;

class RabCategoryController extends Controller
{
    public function index(Project $project)
    {
        return RabCategory::where('project_id', $project->id)
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->with('children');
            }])
            ->get();
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:10',
            'name' => 'required|string',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:rab_categories,id',
            ],
            'sort_order' => 'nullable|integer',
        ]);

        if (isset($validated['parent_id'])) {
            $parent = RabCategory::find($validated['parent_id']);
            if (!$parent || $parent->project_id !== $project->id) {
                return response()->json(['message' => 'Invalid parent_id for this project.'], 422);
            }
        }

        $validated['project_id'] = $project->id;

        $category = RabCategory::create($validated);
        return response()->json($category, 201);
    }

    public function show(RabCategory $rabCategory)
    {
        return $rabCategory->load('items');
    }

    public function update(Request $request, RabCategory $rabCategory)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:10',
            'name' => 'sometimes|string',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:rab_categories,id',
            ],
            'sort_order' => 'nullable|integer',
        ]);

        if (array_key_exists('parent_id', $validated)) {
            if ($validated['parent_id'] === $rabCategory->id) {
                return response()->json(['message' => 'parent_id cannot be the category itself.'], 422);
            }

            if ($validated['parent_id'] !== null) {
                $parent = RabCategory::find($validated['parent_id']);
                if (!$parent || $parent->project_id !== $rabCategory->project_id) {
                    return response()->json(['message' => 'Invalid parent_id for this project.'], 422);
                }

                if ($this->hasCircularParentReference($rabCategory, $parent)) {
                    return response()->json(['message' => 'Circular parent reference is not allowed.'], 422);
                }
            }
        }

        $rabCategory->update($validated);
        return $rabCategory;
    }

    private function hasCircularParentReference(RabCategory $category, RabCategory $newParent): bool
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

    public function destroy(RabCategory $rabCategory)
    {
        $rabCategory->delete();
        return response()->noContent();
    }
}
