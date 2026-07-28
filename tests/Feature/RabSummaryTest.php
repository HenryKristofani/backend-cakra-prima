<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Project;
use App\Models\RabCategory;
use App\Models\RabItem;
use App\Models\ProgressReport;

class RabSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_rab_summary_matches_expected_structure()
    {
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);
        $cat = RabCategory::create(['project_id' => $project->id, 'code' => 'A', 'name' => 'Cat A']);

        $item = RabItem::create(['category_id' => $cat->id, 'description' => 'I1', 'volume' => 2, 'unit' => 'm2', 'unit_price' => 1000, 'status' => 'aktif']);
        ProgressReport::create(['rab_item_id' => $item->id, 'report_date' => now()->toDateString(), 'percentage_complete' => 50]);

        $resp = $this->getJson("/api/projects/{$project->id}/rab-summary");
        $resp->assertStatus(200)->assertJsonStructure([
            'total_rab_aktif',
            'overall_progress_percentage',
            'categories' => [
                ['code','name','items']
            ]
        ]);
    }
}
