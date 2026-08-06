<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RabCategory;
use App\Models\RabItem;
use App\Models\ProgressReport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProgressDetailController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $date = $request->input('date', Carbon::today()->toDateString());

        // Calculate total RAB active for bobot
        $totalActive = $project->total_rab_aktif;

        $latestPercentageSubquery = ProgressReport::select('percentage_complete')
            ->whereColumn('rab_item_id', 'rab_items.id')
            ->where('report_date', '<=', $date)
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->limit(1);

        $latestDateSubquery = ProgressReport::select('report_date')
            ->whereColumn('rab_item_id', 'rab_items.id')
            ->where('report_date', '<=', $date)
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->limit(1);

        $categories = RabCategory::where('project_id', $project->id)->orderBy('sort_order')->get();
        
        $items = $project->rabItems()
            ->select('rab_items.*')
            ->addSelect(['latest_percentage_complete' => $latestPercentageSubquery])
            ->addSelect(['last_report_date' => $latestDateSubquery])
            ->orderBy('sort_order')
            ->get();

        // Build the tree in memory
        $itemsByCategoryId = $items->groupBy('category_id');

        $buildCategoryTree = function ($parentId = null) use (&$buildCategoryTree, $categories, $itemsByCategoryId, $totalActive) {
            $cats = $categories->where('parent_id', $parentId);
            
            return $cats->map(function ($cat) use (&$buildCategoryTree, $itemsByCategoryId, $totalActive) {
                $children = $buildCategoryTree($cat->id);
                $itemsList = $itemsByCategoryId->get($cat->id, collect());
                
                $itemsPayload = $itemsList->map(function ($item) use ($totalActive) {
                    $bobot = $totalActive > 0 ? ($item->total_price / $totalActive * 100) : 0;
                    $latest = (float) ($item->latest_percentage_complete ?? 0);
                    
                    return [
                        'id' => $item->id,
                        'description' => $item->description,
                        'total_price' => (float) $item->total_price,
                        'bobot_percentage' => round($bobot, 4),
                        'latest_percentage_complete' => $latest,
                        'weighted_contribution' => round($bobot * $latest / 100, 4),
                        'last_report_date' => $item->last_report_date ? Carbon::parse($item->last_report_date)->toDateString() : null,
                    ];
                });

                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'code' => $cat->code,
                    'items' => $itemsPayload,
                    'children' => $children->values(),
                ];
            });
        };

        $resultTree = $buildCategoryTree(null)->values();

        return response()->json([
            'date' => $date,
            'categories' => $resultTree,
            'total_rab_aktif' => (float) $totalActive,
        ]);
    }

    public function history(Request $request, RabItem $item)
    {
        $reports = ProgressReport::where('rab_item_id', $item->id)
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $reports
        ]);
    }
}
