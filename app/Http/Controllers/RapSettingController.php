<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RapSetting;
use Illuminate\Http\Request;

class RapSettingController extends Controller
{
    /**
     * Get the effective potongan setting for a project.
     * Also returns the global default if applicable.
     */
    public function show(Project $project)
    {
        $setting = RapSetting::where('project_id', $project->id)->first();
        $global  = RapSetting::whereNull('project_id')->first();

        return response()->json([
            'project_setting'                    => $setting,
            'global_setting'                     => $global,
            'effective_potongan_percentage'  => RapSetting::resolvePotongan($project->id),
        ]);
    }

    /**
     * Upsert a project-specific override for potongan_percentage.
     */
    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'potongan_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $setting = RapSetting::updateOrCreate(
            ['project_id' => $project->id],
            ['potongan_percentage' => $validated['potongan_percentage']]
        );

        return response()->json($setting);
    }

    // ─── Global Settings ────────────────────────────────────────────────────────

    /**
     * Get the global default potongan_percentage.
     */
    public function showGlobal()
    {
        $global = RapSetting::whereNull('project_id')->first();
        return response()->json($global ?? ['potongan_percentage' => 0.0]);
    }

    /**
     * Upsert the global default potongan_percentage.
     */
    public function updateGlobal(Request $request)
    {
        $validated = $request->validate([
            'potongan_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $global = RapSetting::updateOrCreate(
            ['project_id' => null],
            ['potongan_percentage' => $validated['potongan_percentage']]
        );

        return response()->json($global);
    }
}
