<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Project;
use App\Models\RabCategory;
use App\Models\RabItem;

class ProgressReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_and_list_progress_reports()
    {
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);
        $cat = RabCategory::create(['project_id' => $project->id, 'code' => 'A', 'name' => 'Cat A']);
        $item = RabItem::create(['category_id' => $cat->id, 'description' => 'I1', 'volume' => 1, 'unit' => 'ls', 'unit_price' => 1000, 'status' => 'aktif']);

        $resp = $this->postJson("/api/rab-categories/{$cat->id}/items/{$item->id}/progress-reports", [
            'report_date' => now()->toDateString(),
            'percentage_complete' => 50,
        ]);

        $resp->assertStatus(201)->assertJsonFragment(['percentage_complete' => 50]);

        $list = $this->getJson("/api/rab-categories/{$cat->id}/items/{$item->id}/progress-reports");
        $list->assertStatus(200)->assertJsonCount(1);
    }
}
