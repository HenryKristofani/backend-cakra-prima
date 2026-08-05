<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RabCategory;
use App\Models\RabItem;
use App\Models\RapCategory;
use App\Models\RapItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RapGenerateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_generate_rap_from_rab()
    {
        $project = Project::create([
            'name' => 'Test Project',
            'client_name' => 'Client',
            'start_date' => '2024-01-01',
            'budget' => 100000000,
        ]);

        $cat1 = RabCategory::create([
            'project_id' => $project->id,
            'name'       => 'Category 1',
            'code'       => 'A',
            'sort_order' => 1,
        ]);

        $cat2 = RabCategory::create([
            'project_id' => $project->id,
            'parent_id'  => $cat1->id,
            'name'       => 'SubCategory 1',
            'code'       => 'A.1',
            'sort_order' => 2,
        ]);

        // Aktif item
        $item1 = RabItem::create([
            'category_id' => $cat1->id,
            'description' => 'Item Aktif',
            'volume'      => 10,
            'unit'        => 'm',
            'unit_price'  => 1000,
            'status'      => 'aktif',
        ]);

        // Dibatalkan item (should not be copied)
        $item2 = RabItem::create([
            'category_id' => $cat1->id,
            'description' => 'Item Dibatalkan',
            'volume'      => 5,
            'unit'        => 'm2',
            'unit_price'  => 500,
            'status'      => 'dibatalkan',
        ]);

        // Dikurangi item (should be copied)
        $item3 = RabItem::create([
            'category_id' => $cat2->id,
            'description' => 'Item Dikurangi',
            'volume'      => 2,
            'unit'        => 'Ls',
            'unit_price'  => 5000,
            'status'      => 'dikurangi',
        ]);

        $response = $this->postJson("/api/projects/{$project->id}/rap/generate-from-rab");
        $response->assertStatus(200);

        // Verify Categories
        $rapCategories = RapCategory::where('project_id', $project->id)->get();
        $this->assertCount(2, $rapCategories);
        $this->assertEquals('Category 1', $rapCategories->firstWhere('name', 'Category 1')->name);
        
        $rapCat1 = $rapCategories->firstWhere('name', 'Category 1');
        $rapCat2 = $rapCategories->firstWhere('name', 'SubCategory 1');
        $this->assertEquals($rapCat1->id, $rapCat2->parent_id);

        // Verify Items
        $rapItems = RapItem::all();
        $this->assertCount(2, $rapItems); // Item Aktif & Item Dikurangi

        $rapItem1 = $rapItems->firstWhere('description', 'Item Aktif');
        $this->assertNotNull($rapItem1);
        $this->assertEquals(0, $rapItem1->unit_price);
        $this->assertEquals($item1->id, $rapItem1->source_rab_item_id);
        $this->assertEquals($rapCat1->id, $rapItem1->category_id);

        $rapItem3 = $rapItems->firstWhere('description', 'Item Dikurangi');
        $this->assertNotNull($rapItem3);
        $this->assertEquals(0, $rapItem3->unit_price);
        $this->assertEquals($item3->id, $rapItem3->source_rab_item_id);
        $this->assertEquals($rapCat2->id, $rapItem3->category_id);

        // Verify Dibatalkan is skipped
        $this->assertNull($rapItems->firstWhere('description', 'Item Dibatalkan'));
    }

    public function test_cannot_generate_if_rap_already_exists()
    {
        $project = Project::create([
            'name' => 'Test Project',
            'client_name' => 'Client',
            'start_date' => '2024-01-01',
            'budget' => 100000000,
        ]);

        RapCategory::create([
            'project_id' => $project->id,
            'name'       => 'Existing',
        ]);

        $response = $this->postJson("/api/projects/{$project->id}/rap/generate-from-rab");
        $response->assertStatus(422)
                 ->assertJson(['message' => 'RAP sudah pernah di-generate, hapus dulu yang lama kalau mau generate ulang']);
    }
}
