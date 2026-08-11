<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RapCategory;
use App\Models\RapItem;
use App\Models\RapSetting;
use App\Models\RabCategory;
use App\Models\RabItem;
use App\Models\ProgressReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectRapController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────────
    // GET /projects/{project}/rap-items
    // Returns a flat list of all rap_items for this project (for dropdowns)
    // ─────────────────────────────────────────────────────────────────────────────

    public function rapItems(Project $project)
    {
        $items = RapItem::whereHas('category', fn ($q) => $q->where('project_id', $project->id))
            ->with('category:id,sort_order')
            ->orderBy(
                RapCategory::select('sort_order')
                    ->whereColumn('rap_categories.id', 'rap_items.category_id')
                    ->limit(1)
            )
            ->orderBy('sort_order')
            ->get(['id', 'category_id', 'description', 'sort_order'])
            ->map(fn ($item) => [
                'id'          => $item->id,
                'description' => $item->description,
            ]);

        return response()->json($items);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // POST /projects/{project}/rap/generate-from-rab
    // ─────────────────────────────────────────────────────────────────────────────

    public function generateFromRab(Project $project)
    {
        if (RapCategory::where('project_id', $project->id)->exists()) {
            return response()->json(['message' => 'RAP sudah pernah di-generate, hapus dulu yang lama kalau mau generate ulang'], 422);
        }

        return DB::transaction(function () use ($project) {
            $rabCategories = RabCategory::where('project_id', $project->id)
                ->with(['items' => function ($query) {
                    $query->where('status', '!=', 'dibatalkan');
                }])
                ->get();

            // Map old category ID to new category ID
            $categoryIdMap = [];

            // 1. Create categories (flattened or nested doesn't matter much if we re-map parent_id correctly, but RabCategory can be nested)
            // Let's iterate in a way that respects hierarchy (parents first).
            // Usually, parent_id is smaller or inserted first.
            $sortedCategories = $rabCategories->sortBy('parent_id');

            foreach ($sortedCategories as $rabCategory) {
                $newParentId = $rabCategory->parent_id ? ($categoryIdMap[$rabCategory->parent_id] ?? null) : null;
                
                $rapCategory = RapCategory::create([
                    'project_id' => $project->id,
                    'parent_id'  => $newParentId,
                    'code'       => $rabCategory->code,
                    'name'       => $rabCategory->name,
                    'sort_order' => $rabCategory->sort_order,
                ]);

                $categoryIdMap[$rabCategory->id] = $rapCategory->id;

                foreach ($rabCategory->items as $rabItem) {
                    RapItem::create([
                        'category_id'        => $rapCategory->id,
                        'description'        => $rabItem->description,
                        'volume'             => $rabItem->volume,
                        'unit'               => $rabItem->unit,
                        'unit_price'         => 0, // start from 0
                        'sort_order'         => $rabItem->sort_order,
                        'source_rab_item_id' => $rabItem->id,
                    ]);
                }
            }

            return response()->json(['message' => 'RAP berhasil di-generate dari RAB']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // GET /projects/{project}/laba-rugi
    // ─────────────────────────────────────────────────────────────────────────────

    public function labaRugi(Project $project)
    {
        $pajak = RapSetting::resolvePajak($project->id);

        $items = RapItem::whereHas('category', fn ($q) => $q->where('project_id', $project->id))
            ->with('category')
            ->get();

        $totalRencana   = 0.0;
        $totalRealisasi = 0.0;

        $rows = $items->map(function (RapItem $item) use (&$totalRencana, &$totalRealisasi) {
            $totalPrice         = $item->total_price;
            $realisasi          = $item->total_realisasi;
            $selisih            = $item->selisih_laba_rugi;

            $totalRencana   += $totalPrice;
            $totalRealisasi += $realisasi;

            $statusLabel = match (true) {
                $selisih > 0 => 'untung',
                $selisih < 0 => 'rugi',
                default      => 'impas',
            };

            return [
                'id'                => $item->id,
                'description'       => $item->description,
                'total_price'       => $totalPrice,
                'total_realisasi'   => $realisasi,
                'selisih_laba_rugi' => $selisih,
                'status_label'      => $statusLabel,
            ];
        });

        $totalSelisih = round($totalRencana - $totalRealisasi, 2);

        return response()->json([
            'items'   => $rows->values(),
            'summary' => [
                'total_rencana'         => round($totalRencana, 2),
                'total_realisasi'       => round($totalRealisasi, 2),
                'total_selisih'         => $totalSelisih,
                'status_label'          => match (true) {
                    $totalSelisih > 0 => 'untung',
                    $totalSelisih < 0 => 'rugi',
                    default           => 'impas',
                },
                'pajak_percentage'      => $pajak,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // GET /projects/{project}/progress-timeline?group_by=day|week|month
    // ─────────────────────────────────────────────────────────────────────────────

    public function progressTimeline(Request $request, Project $project)
    {
        $groupBy = $request->input('group_by', 'week');
        if (!in_array($groupBy, ['day', 'week', 'month'])) {
            return response()->json(['message' => "group_by must be 'day', 'week', or 'month'."], 422);
        }

        // All active RAB items for this project → needed for bobot calculation
        $allActiveItems = RabItem::whereHas('category', fn ($q) => $q->where('project_id', $project->id))
            ->where('status', 'aktif')
            ->get();

        $totalActive = $allActiveItems->sum(fn ($i) => (float) $i->volume * (float) $i->unit_price);

        if ($totalActive <= 0) {
            return response()->json(['data' => []]);
        }

        // Build bobot map: rab_item_id (int) → bobot_percentage (float)
        $bobotMap = [];
        foreach ($allActiveItems as $item) {
            $price = (float) $item->volume * (float) $item->unit_price;
            $bobotMap[$item->id] = round(($price / $totalActive) * 100, 4);
        }

        // All progress reports for this project's RAB items, ordered by date
        $rabItemIds = $allActiveItems->pluck('id')->toArray();

        $allReports = ProgressReport::whereIn('rab_item_id', $rabItemIds)
            ->orderBy('report_date')
            ->get();

        if ($allReports->isEmpty()) {
            return response()->json(['data' => []]);
        }

        // Use plain string dates to avoid Carbon Immutable mutation issues
        $firstDateStr = is_string($allReports->first()->report_date)
            ? $allReports->first()->report_date
            : $allReports->first()->report_date->toDateString();

        $lastDateStr = is_string($allReports->last()->report_date)
            ? $allReports->last()->report_date
            : $allReports->last()->report_date->toDateString();

        $dates = $this->buildDateSeries($firstDateStr, $lastDateStr, $groupBy);

        $timeline = [];
        foreach ($dates as $dateStr) {
            // Latest report per rab_item where report_date <= $dateStr
            $latestPctByItem = ProgressReport::whereIn('rab_item_id', $rabItemIds)
                ->whereDate('report_date', '<=', $dateStr)
                ->orderByDesc('report_date')
                ->get()
                ->groupBy('rab_item_id')
                ->map(fn ($reports) => (float) $reports->first()->percentage_complete)
                ->all(); // plain PHP array

            $overallProgress = 0.0;
            foreach ($latestPctByItem as $itemId => $pct) {
                $bobot = $bobotMap[(int) $itemId] ?? 0.0;
                $overallProgress += $bobot * ($pct / 100.0);
            }

            $timeline[] = [
                'date'                        => $dateStr,
                'overall_progress_percentage' => round($overallProgress, 2),
            ];
        }

        return response()->json(['data' => $timeline]);
    }

    // ─── Private Helper ───────────────────────────────────────────────────────────

    /**
     * Build an array of date strings for the series between $fromStr and $toStr.
     * Uses mutable Carbon internally and operates on plain strings to avoid
     * CarbonImmutable mutation issues.
     */
    private function buildDateSeries(string $fromStr, string $toStr, string $groupBy): array
    {
        // Force mutable Carbon
        $current = Carbon::parse($fromStr);
        $end     = Carbon::parse($toStr);

        // Snap $current to start of the period
        match ($groupBy) {
            'week'  => $current->startOfWeek(),
            'month' => $current->startOfMonth(),
            default => $current->startOfDay(),
        };

        $dates = [];

        while ($current->lte($end)) {
            // Snap to end of period, but cap at $toStr
            $periodEnd = $current->copy();
            match ($groupBy) {
                'week'  => $periodEnd->endOfWeek(),
                'month' => $periodEnd->endOfMonth(),
                default => $periodEnd->endOfDay(),
            };

            $snapDate = $periodEnd->gt($end) ? $end->toDateString() : $periodEnd->toDateString();
            $dates[]  = $snapDate;

            // Advance current by 1 period — reassign to handle immutable variants
            $current = match ($groupBy) {
                'week'  => $current->addWeek()->startOfWeek(),
                'month' => $current->addMonth()->startOfMonth(),
                default => $current->addDay()->startOfDay(),
            };
        }

        // Deduplicate (same date might appear if $to falls inside a period)
        return array_values(array_unique($dates));
    }
}
