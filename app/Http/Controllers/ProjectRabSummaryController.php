<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectRabSummaryController extends Controller
{
    public function __invoke(Project $project, Request $request)
    {
        $totalActive = $project->total_rab_aktif;

        $categories = \App\Models\RabCategory::where('project_id', $project->id)
            ->with(['items' => function ($q) {
                $q->where('status', 'aktif');
            }])->get();

        $resultCats = $categories->map(function ($cat) use ($totalActive) {
            $items = $cat->items->map(function ($item) use ($totalActive) {
                $totalPrice = (float) $item->total_price;
                $bobot = $totalActive > 0 ? ($totalPrice / $totalActive) * 100.0 : 0.0;
                $latest = (float) $item->latest_progress_percentage;
                $weighted = ($bobot * $latest) / 100.0;

                return [
                    'description' => $item->description,
                    'volume' => $item->volume,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'total_price' => $totalPrice,
                    'bobot_percentage' => round($bobot, 2),
                    'latest_progress_percentage' => $latest,
                    'weighted_contribution' => round($weighted, 2),
                    'status' => $item->status,
                ];
            });

            return [
                'code' => $cat->code,
                'name' => $cat->name,
                'items' => $items,
            ];
        })->values();

        return response()->json([
            'total_rab_aktif' => (float) $totalActive,
            'overall_progress_percentage' => round($project->overall_progress_percentage, 2),
            'categories' => $resultCats,
        ]);
    }
}
