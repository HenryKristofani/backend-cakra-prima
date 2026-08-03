<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RabCategory;
use App\Models\RabItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RabItemBulkTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $project;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::create([
            'name' => 'Project Test',
            'status' => 'aktif',
            'location' => 'Jakarta',
            'rab_date' => now()
        ]);
        $this->category = RabCategory::create([
            'project_id' => $this->project->id,
            'name' => 'Kategori Test',
            'code' => 'A',
            'sort_order' => 1,
        ]);
    }

    public function test_bulk_create_items_successfully()
    {
        $payload = [
            'items' => [
                [
                    'description' => 'Item 1',
                    'volume' => 10,
                    'unit' => 'm2',
                    'unit_price' => 50000,
                    'sort_order' => 1,
                    'status' => 'aktif',
                ],
                [
                    'description' => 'Item 2',
                    'volume' => 5,
                    'unit' => 'm3',
                    'unit_price' => 100000,
                    'sort_order' => 2,
                    'status' => 'aktif',
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->postJson(
            "/api/rab-categories/{$this->category->id}/items/bulk",
            $payload
        );

        $response->assertStatus(201)
                 ->assertJsonCount(2);

        $this->assertDatabaseHas('rab_items', ['description' => 'Item 1', 'category_id' => $this->category->id]);
        $this->assertDatabaseHas('rab_items', ['description' => 'Item 2', 'category_id' => $this->category->id]);

        // Check if calculated fields are returned
        $response->assertJsonStructure([
            '*' => ['id', 'description', 'total_price', 'bobot_percentage']
        ]);
    }

    public function test_bulk_create_rollback_on_partial_failure()
    {
        $payload = [
            'items' => [
                [
                    'description' => 'Valid Item',
                    'volume' => 10,
                    'unit' => 'm2',
                    'unit_price' => 50000,
                    'status' => 'aktif',
                ],
                [
                    'description' => 'Invalid Item',
                    // Missing volume
                    'unit' => 'm3',
                    'unit_price' => 100000,
                    'status' => 'aktif',
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->postJson(
            "/api/rab-categories/{$this->category->id}/items/bulk",
            $payload
        );

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['items.1.volume']);

        // Assert atomicity (Valid Item should NOT be saved)
        $this->assertDatabaseMissing('rab_items', ['description' => 'Valid Item']);
    }

    public function test_bulk_update_items_successfully()
    {
        $item1 = RabItem::create([
            'category_id' => $this->category->id,
            'description' => 'Old Item 1',
            'volume' => 10,
            'unit' => 'm2',
            'unit_price' => 50000,
            'status' => 'aktif',
        ]);

        $item2 = RabItem::create([
            'category_id' => $this->category->id,
            'description' => 'Old Item 2',
            'volume' => 5,
            'unit' => 'm3',
            'unit_price' => 100000,
            'status' => 'aktif',
        ]);

        $payload = [
            'items' => [
                [
                    'id' => $item1->id,
                    'description' => 'Updated Item 1',
                    'volume' => 15,
                    'unit' => 'm2',
                    'unit_price' => 60000,
                    'status' => 'aktif',
                ],
                [
                    'id' => $item2->id,
                    'description' => 'Updated Item 2',
                    'volume' => 5,
                    'unit' => 'm3',
                    'unit_price' => 120000,
                    'status' => 'aktif',
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->putJson(
            "/api/rab-categories/{$this->category->id}/items/bulk",
            $payload
        );

        $response->assertStatus(200)
                 ->assertJsonCount(2);

        $this->assertDatabaseHas('rab_items', ['id' => $item1->id, 'description' => 'Updated Item 1']);
        $this->assertDatabaseHas('rab_items', ['id' => $item2->id, 'description' => 'Updated Item 2']);
    }

    public function test_bulk_update_rollback_on_partial_failure()
    {
        $item = RabItem::create([
            'category_id' => $this->category->id,
            'description' => 'Old Item 1',
            'volume' => 10,
            'unit' => 'm2',
            'unit_price' => 50000,
            'status' => 'aktif',
        ]);

        $payload = [
            'items' => [
                [
                    'id' => $item->id,
                    'description' => 'Valid Update',
                    'volume' => 20,
                    'unit' => 'm2',
                    'unit_price' => 60000,
                    'status' => 'aktif',
                ],
                [
                    'id' => 9999, // Invalid ID
                    'description' => 'Invalid Update',
                    'volume' => 5,
                    'unit' => 'm3',
                    'unit_price' => 120000,
                    'status' => 'aktif',
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->putJson(
            "/api/rab-categories/{$this->category->id}/items/bulk",
            $payload
        );

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['items.1.id']);

        // Assert atomicity (Valid Update should NOT be applied)
        $this->assertDatabaseHas('rab_items', [
            'id' => $item->id,
            'description' => 'Old Item 1', // still old
            'volume' => 10
        ]);
    }

    public function test_bulk_create_with_dikurangi_status_has_zero_bobot()
    {
        // First create an active item so total active is > 0
        RabItem::create([
            'category_id' => $this->category->id,
            'description' => 'Existing Active',
            'volume' => 1,
            'unit' => 'ls',
            'unit_price' => 100000,
            'status' => 'aktif',
        ]);

        $payload = [
            'items' => [
                [
                    'description' => 'Pekerjaan Kurang',
                    'volume' => 1,
                    'unit' => 'ls',
                    'unit_price' => 50000,
                    'status' => 'dikurangi',
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->postJson(
            "/api/rab-categories/{$this->category->id}/items/bulk",
            $payload
        );

        $response->assertStatus(201);
        
        // Check the returned JSON structure for bobot_percentage
        $responseData = $response->json();
        $this->assertEquals(0.0, $responseData[0]['bobot_percentage']);
        
        // Assert it exists in DB with correct status
        $this->assertDatabaseHas('rab_items', [
            'description' => 'Pekerjaan Kurang',
            'status' => 'dikurangi'
        ]);
    }
}
