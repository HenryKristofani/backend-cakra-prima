<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Project;
use App\Models\RabCategory;
use App\Models\RabItem;

use App\Models\User;

class ProgressReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_and_list_progress_reports()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);
        $cat = RabCategory::create(['project_id' => $project->id, 'code' => 'A', 'name' => 'Cat A']);
        $item = RabItem::create(['category_id' => $cat->id, 'description' => 'I1', 'volume' => 1, 'unit' => 'ls', 'unit_price' => 1000, 'status' => 'aktif']);

        $this->actingAs($user)
            ->postJson("/api/rab-categories/{$cat->id}/items/{$item->id}/progress-reports", [
            'report_date' => '2026-07-01',
            'percentage_complete' => 25,
            'notes' => 'Test',
        ])->assertCreated();

        $this->actingAs($user)
            ->getJson("/api/rab-categories/{$cat->id}/items/{$item->id}/progress-reports")
            ->assertOk()
            ->assertJsonCount(1);
    }
}
