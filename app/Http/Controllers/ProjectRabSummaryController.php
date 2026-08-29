<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectRabSummaryController extends Controller
{
    public function __invoke(Project $project, Request $request)
    {
        $activeItems = $project->rabItems()
            ->whereIn('status', ['aktif', 'dikurangi'])
            ->get();
        $totalActive = '0';
        foreach ($activeItems as $item) {
            $totalActive = bcadd($totalActive, (string) $item->total_price, 10);
        }
        $totalActive = (float) $totalActive;

        $rootCategories = \App\Models\RabCategory::where('project_id', $project->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $deductions = \App\Models\RabItem::whereHas('category', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })->where('status', 'dikurangi')->with('progressReports')->get();

        $overallTotalPercentage = 0.0;

        $resultCats = $rootCategories->map(function ($category) use ($totalActive, &$overallTotalPercentage) {
            return $this->buildCategoryPayload($category, $totalActive, $overallTotalPercentage);
        })->values();

        $totalDeduction = '0';
        foreach ($deductions as $d) {
            $totalDeduction = bcadd($totalDeduction, (string) $d->total_price, 10);
        }
        $totalDeduction = (float) $totalDeduction;
        $finalTotal = $totalActive - $totalDeduction;
        $roundedTotal = ceil($finalTotal / 1000) * 1000;

        return response()->json([
            'total_rab_aktif' => (float) $totalActive,
            'total_deduction' => $totalDeduction,
            'final_total' => $finalTotal,
            'rounded_total' => (float) $roundedTotal,
            'overall_progress_percentage' => round($overallTotalPercentage, 2),
            'categories' => $resultCats,
            'deductions' => $deductions->map(function ($item) {
                return [
                    'description' => $item->description,
                    'volume' => $item->volume,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'total_price' => (float) $item->total_price,
                    'status' => $item->status,
                ];
            })->values(),
        ]);
    }

    private function buildCategoryPayload($category, float $totalActive, float &$overallTotalPercentage): array
    {
        $items = $category->items()->with('progressReports')->get()->map(function ($item) use ($totalActive, &$overallTotalPercentage) {
            $totalPrice = (float) $item->total_price;
            $latest = (float) $item->latest_progress_percentage;
            $bobot = 0.0;
            $totalPercentage = 0.0;

            if ($totalActive > 0) {
                $bobot = round(($totalPrice / $totalActive) * 100.0, 2);
            }

            if ($item->status === 'aktif' && $totalActive > 0) {
                $totalPercentage = round($bobot * ($latest / 100.0), 2);
                $overallTotalPercentage += $totalPercentage;
            }

            return [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'description' => $item->description,
                'volume' => $item->volume,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'total_price' => $totalPrice,
                'bobot_percentage' => $item->status === 'dikurangi' ? 0.0 : $bobot,
                'latest_progress_percentage' => $latest,
                'total_percentage' => $item->status === 'dikurangi' ? 0.0 : $totalPercentage,
                'status' => $item->status,
            ];
        })->values();

        $children = \App\Models\RabCategory::where('parent_id', $category->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($child) use ($totalActive, &$overallTotalPercentage) {
                return $this->buildCategoryPayload($child, $totalActive, $overallTotalPercentage);
            })->values();

        $childBobot = $children->sum(fn ($child) => $child['total_bobot_percentage']);
        $childProgress = $children->sum(fn ($child) => $child['total_progress_percentage']);
        $totalBobot = $items->sum(fn ($item) => $item['bobot_percentage']) + $childBobot;
        $totalProgress = $items->sum(fn ($item) => $item['total_percentage']) + $childProgress;

        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'total_bobot_percentage' => round($totalBobot, 2),
            'total_progress_percentage' => round($totalProgress, 2),
            'children' => $children,
            'items' => $items,
        ];
    }
    public function exportExcel(Project $project, Request $request)
    {
        $summaryResponse = $this->__invoke($project, $request);
        $data = $summaryResponse->getData(true);
        
        $filename = 'RAB-' . \Illuminate\Support\Str::slug($project->name) . '-' . now()->format('Ymd') . '.xlsx';
        
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ProjectRabExport($data, $project), 
            $filename
        );
    }
}
