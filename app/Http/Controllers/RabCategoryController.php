<?php

namespace App\Http\Controllers;

use App\Models\RabCategory;
use App\Models\Project;
use Illuminate\Http\Request;

class RabCategoryController extends Controller
{
    public function index(Project $project)
    {
        return RabCategory::where('project_id', $project->id)->with('items')->get();
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:10',
            'name' => 'required|string',
            'sort_order' => 'nullable|integer',
        ]);

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
            'sort_order' => 'nullable|integer',
        ]);

        $rabCategory->update($validated);
        return $rabCategory;
    }

    public function destroy(RabCategory $rabCategory)
    {
        $rabCategory->delete();
        return response()->noContent();
    }
}
