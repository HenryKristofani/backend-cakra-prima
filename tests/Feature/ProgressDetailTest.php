<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RabCategory;
use App\Models\RabItem;
use App\Models\ProgressReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->project = Project::create(['name' => 'Timeline Project', 'status' => 'active']);
    }

    private function createRabItem(float $unitPrice, float $volume, RabCategory $category = null): RabItem
    {
        if (!$category) {
            $category = RabCategory::create([
                'project_id' => $this->project->id,
                'name' => 'Category 1',
                'sort_order' => 1
            ]);
        }

        return RabItem::create([
            'category_id' => $category->id,
            'description' => 'Test Item',
            'volume' => $volume,
            'unit' => 'm3',
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $volume,
            'status' => 'aktif',
            'sort_order' => 1
        ]);
    }

    public function test_progress_detail_returns_correct_tree_and_latest_progress()
    {
        $cat1 = RabCategory::create(['project_id' => $this->project->id, 'name' => 'Cat 1', 'sort_order' => 1]);
        $cat2 = RabCategory::create(['project_id' => $this->project->id, 'name' => 'Cat 2', 'parent_id' => $cat1->id, 'sort_order' => 1]);

        $item1 = $this->createRabItem(100_000, 1, $cat1); // 100k
        $item2 = $this->createRabItem(100_000, 1, $cat2); // 100k

        ProgressReport::create([
            'rab_item_id' => $item1->id,
            'report_date' => '2026-07-01',
            'percentage_complete' => 20,
        ]);
        ProgressReport::create([
            'rab_item_id' => $item1->id,
            'report_date' => '2026-07-05',
            'percentage_complete' => 60,
        ]);

        ProgressReport::create([
            'rab_item_id' => $item2->id,
            'report_date' => '2026-07-03',
            'percentage_complete' => 40,
        ]);

        // Request date 2026-07-04 -> item1 should be 20%, item2 should be 40%
        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/progress-detail?date=2026-07-04")
            ->assertOk();

        $response->assertJsonPath('total_rab_aktif', 200000);
        
        $data = $response->json('categories');
        
        $this->assertCount(1, $data);
        $this->assertEquals('Cat 1', $data[0]['name']);
        
        // Check item1 inside Cat 1
        $this->assertCount(1, $data[0]['items']);
        $this->assertEquals(20, $data[0]['items'][0]['latest_percentage_complete']);
        $this->assertEquals('2026-07-01', $data[0]['items'][0]['last_report_date']);
        $this->assertEquals(50, $data[0]['items'][0]['bobot_percentage']); // 100k / 200k
        
        // Check Cat 2 inside Cat 1
        $this->assertCount(1, $data[0]['children']);
        $this->assertEquals('Cat 2', $data[0]['children'][0]['name']);
        
        // Check item2 inside Cat 2
        $this->assertCount(1, $data[0]['children'][0]['items']);
        $this->assertEquals(40, $data[0]['children'][0]['items'][0]['latest_percentage_complete']);
        $this->assertEquals('2026-07-03', $data[0]['children'][0]['items'][0]['last_report_date']);
    }

    public function test_progress_detail_handles_items_with_no_reports()
    {
        $item = $this->createRabItem(100_000, 1);

        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/progress-detail")
            ->assertOk();

        $data = $response->json('categories');
        
        $this->assertEquals(0, $data[0]['items'][0]['latest_percentage_complete']);
        $this->assertNull($data[0]['items'][0]['last_report_date']);
    }

    public function test_history_endpoint_returns_correct_data()
    {
        $item = $this->createRabItem(100_000, 1);

        ProgressReport::create(['rab_item_id' => $item->id, 'report_date' => '2026-07-01', 'percentage_complete' => 20]);
        ProgressReport::create(['rab_item_id' => $item->id, 'report_date' => '2026-07-05', 'percentage_complete' => 60]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/rab-items/{$item->id}/progress-reports")
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals(60, $data[0]['percentage_complete']); // newest first
        $this->assertEquals(20, $data[1]['percentage_complete']);
    }
}
