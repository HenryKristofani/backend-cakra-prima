<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RabCategory;
use App\Models\RabItem;
use App\Models\ProgressReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressTimelineEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user    = User::factory()->create();
        $this->project = Project::create(['name' => 'Timeline Project', 'status' => 'active']);
    }

    private function createRabItem(float $unitPrice, float $volume): RabItem
    {
        $cat = RabCategory::create([
            'project_id' => $this->project->id,
            'name'       => 'Kategori',
            'sort_order' => 0,
        ]);

        return RabItem::create([
            'category_id' => $cat->id,
            'description' => 'Item',
            'volume'      => $volume,
            'unit'        => 'm2',
            'unit_price'  => $unitPrice,
            'sort_order'  => 0,
            'status'      => 'aktif',
        ]);
    }

    public function test_progress_timeline_returns_correct_structure(): void
    {
        $item = $this->createRabItem(100_000, 1);

        ProgressReport::create([
            'rab_item_id'         => $item->id,
            'report_date'         => '2026-06-01',
            'percentage_complete' => 50,
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/progress-timeline")
            ->assertOk()
            ->assertJsonStructure(['data' => [['date', 'overall_progress_percentage']]]);
    }

    public function test_progress_timeline_empty_when_no_reports(): void
    {
        $this->createRabItem(100_000, 1);

        $data = $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/progress-timeline")
            ->assertOk()
            ->json('data');

        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    public function test_progress_timeline_cumulative_calculation_is_correct(): void
    {
        // Two items, each 100k × 1 = 100k → each 50% bobot
        $item1 = $this->createRabItem(100_000, 1);
        $item2 = $this->createRabItem(100_000, 1);

        // Item1: 20% on day 1, 60% on day 3
        ProgressReport::create(['rab_item_id' => $item1->id, 'report_date' => '2026-07-01', 'percentage_complete' => 20]);
        ProgressReport::create(['rab_item_id' => $item1->id, 'report_date' => '2026-07-03', 'percentage_complete' => 60]);

        // Item2: 40% on day 2
        ProgressReport::create(['rab_item_id' => $item2->id, 'report_date' => '2026-07-02', 'percentage_complete' => 40]);

        // Use group_by=day to get precise per-day snapshots
        $data = $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/progress-timeline?group_by=day")
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($data);

        // Results are ordered by date
        $dates = array_column($data, 'date');
        $this->assertEquals($dates, array_values(array_unique($dates)), 'Dates should be unique');

        $sorted = $dates;
        sort($sorted);
        $this->assertEquals($sorted, $dates, 'Dates should be in ascending order');

        // Find day-1 snapshot: only item1 at 20% → overall = 50% * 20% = 10%
        $day1 = collect($data)->firstWhere('date', '2026-07-01');
        $this->assertNotNull($day1);
        $this->assertEquals(10.0, $day1['overall_progress_percentage']);

        // Find day-2 snapshot: item1 at 20%, item2 at 40% → 50%*20% + 50%*40% = 10+20 = 30%
        $day2 = collect($data)->firstWhere('date', '2026-07-02');
        $this->assertNotNull($day2);
        $this->assertEquals(30.0, $day2['overall_progress_percentage']);

        // Find day-3 snapshot: item1 at 60% (latest), item2 at 40% → 50%*60% + 50%*40% = 30+20 = 50%
        $day3 = collect($data)->firstWhere('date', '2026-07-03');
        $this->assertNotNull($day3);
        $this->assertEquals(50.0, $day3['overall_progress_percentage']);
    }

    public function test_progress_timeline_rejects_invalid_group_by(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/progress-timeline?group_by=year")
            ->assertStatus(422);
    }

    public function test_progress_timeline_defaults_to_week_grouping(): void
    {
        $item = $this->createRabItem(100_000, 1);
        ProgressReport::create(['rab_item_id' => $item->id, 'report_date' => '2026-07-01', 'percentage_complete' => 50]);

        // No group_by param — should default to week
        $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/progress-timeline")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
