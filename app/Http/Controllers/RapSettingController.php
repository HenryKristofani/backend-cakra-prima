<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RapSetting;
use Illuminate\Http\Request;

class RapSettingController extends Controller
{
    /**
     * GET /projects/{project}/rap-setting
     * Returns the effective setting for this project (project-specific or global fallback).
     */
    public function show(Project $project)
    {
        $projectSetting = RapSetting::where('project_id', $project->id)->first();
        $globalSetting  = RapSetting::whereNull('project_id')->first();

        return response()->json([
            'project_setting' => $projectSetting,
            'global_setting'  => $globalSetting,
            'effective_potongan_percentage' => RapSetting::resolvePotongan($project->id),
        ]);
    }

    /**
     * PUT /projects/{project}/rap-setting
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

    /**
     * GET /rap-setting/global
     * Returns the global (project_id = NULL) default setting.
     */
    public function showGlobal()
    {
        $global = RapSetting::whereNull('project_id')->first();
        return response()->json($global ?? ['potongan_percentage' => 0.0]);
    }

    /**
     * PUT /rap-setting/global
     * Upsert the global default potongan_percentage.
     */
    public function updateGlobal(Request $request)
    {
        $validated = $request->validate([
            'potongan_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $setting = RapSetting::updateOrCreate(
            ['project_id' => null],
            ['potongan_percentage' => $validated['potongan_percentage']]
        );

        return response()->json($setting);
    }
}
