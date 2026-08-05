<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RapSetting;
use Illuminate\Http\Request;

class RapSettingController extends Controller
{
    /**
     * Get the effective pajak setting for a project.
     * Also returns the global default if applicable.
     */
    public function show(Project $project)
    {
        $setting = RapSetting::where('project_id', $project->id)->first();
        $global  = RapSetting::whereNull('project_id')->first();

        return response()->json([
            'project_setting'               => $setting,
            'global_setting'                => $global,
            'effective_pajak_percentage' => RapSetting::resolvePajak($project->id),
        ]);
    }

    /**
     * Upsert a project-specific override for pajak_percentage.
     */
    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'pajak_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $setting = RapSetting::updateOrCreate(
            ['project_id' => $project->id],
            ['pajak_percentage' => $validated['pajak_percentage']]
        );

        return response()->json($setting);
    }

    // ─── Global Settings ────────────────────────────────────────────────────────

    /**
     * Get the global default pajak_percentage.
     */
    public function showGlobal()
    {
        $global = RapSetting::whereNull('project_id')->first();
        return response()->json($global ?? ['pajak_percentage' => 0.0]);
    }

    /**
     * Upsert the global default pajak_percentage.
     */
    public function updateGlobal(Request $request)
    {
        $validated = $request->validate([
            'pajak_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $global = RapSetting::updateOrCreate(
            ['project_id' => null],
            ['pajak_percentage' => $validated['pajak_percentage']]
        );

        return response()->json($global);
    }
}
