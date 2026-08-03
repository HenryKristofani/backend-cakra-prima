<?php

namespace App\Http\Controllers;

use App\Models\RabCategory;
use App\Models\RabItem;
use App\Models\ProgressReport;
use Illuminate\Http\Request;

class ProgressReportController extends Controller
{
    public function index(RabCategory $rabCategory, RabItem $rabItem)
    {
        return $rabItem->progressReports()->orderByDesc('report_date')->get();
    }

    public function store(Request $request, RabCategory $rabCategory, RabItem $rabItem)
    {
        $validated = $request->validate([
            'report_date' => 'required|date',
            'percentage_complete' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $validated['rab_item_id'] = $rabItem->id;

        $report = ProgressReport::create($validated);
        return response()->json($report, 201);
    }
}
