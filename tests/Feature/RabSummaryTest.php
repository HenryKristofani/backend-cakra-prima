<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Project;
use App\Models\RabCategory;
use App\Models\RabItem;
use App\Models\ProgressReport;
use App\Models\User;

class RabSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_rab_summary_matches_expected_structure()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'status' => 'aktif', 'location' => 'Jakarta', 'rab_date' => '2026-07-30']);
        $parentCategory = RabCategory::create(['project_id' => $project->id, 'code' => 'A', 'name' => 'Cat A']);
        $childCategory = RabCategory::create(['project_id' => $project->id, 'code' => 'A1', 'name' => 'Cat A1', 'parent_id' => $parentCategory->id]);

        $activeItem = RabItem::create(['category_id' => $parentCategory->id, 'description' => 'Item aktif', 'volume' => 2, 'unit' => 'm2', 'unit_price' => 1000, 'status' => 'aktif']);
        $childActiveItem = RabItem::create(['category_id' => $childCategory->id, 'description' => 'Item anak', 'volume' => 1, 'unit' => 'm2', 'unit_price' => 1000, 'status' => 'aktif']);
        $deductionItem = RabItem::create(['category_id' => $childCategory->id, 'description' => 'Pengurang', 'volume' => 2, 'unit' => 'm2', 'unit_price' => 1000, 'status' => 'dikurangi']);

        ProgressReport::create(['rab_item_id' => $activeItem->id, 'report_date' => now()->toDateString(), 'percentage_complete' => 50]);
        ProgressReport::create(['rab_item_id' => $childActiveItem->id, 'report_date' => now()->toDateString(), 'percentage_complete' => 10]);

        $resp = $this->actingAs($user)->getJson("/api/projects/{$project->id}/rab-summary");
        $resp->assertStatus(200)
            ->assertJsonPath('total_rab_aktif', 5000)
            ->assertJsonPath('total_deduction', 2000)
            ->assertJsonPath('final_total', 3000)
            ->assertJsonPath('rounded_total', 3000)
            ->assertJsonPath('overall_progress_percentage', 22)
            ->assertJsonPath('categories.0.total_bobot_percentage', 60)
            ->assertJsonPath('categories.0.total_progress_percentage', 22)
            ->assertJsonPath('deductions.0.description', 'Pengurang')
            ->assertJsonPath('deductions.0.total_price', 2000)
            ->assertJsonPath('categories.0.children.0.total_bobot_percentage', 20)
            ->assertJsonPath('categories.0.children.0.total_progress_percentage', 2);
    }
}
