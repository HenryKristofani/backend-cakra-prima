<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Project;
use App\Models\RabCategory;
use App\Models\User;

class RabItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_update_delete_item()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);
        $cat = RabCategory::create(['project_id' => $project->id, 'code' => 'A', 'name' => 'Cat A']);

        $resp = $this->actingAs($user)->postJson("/api/rab-categories/{$cat->id}/items", [
            'description' => 'Item 1',
            'volume' => 2,
            'unit' => 'm2',
            'unit_price' => 100000,
            'status' => 'aktif'
        ]);

        $resp->assertStatus(201)->assertJsonFragment(['description' => 'Item 1']);

        $itemId = $resp->json('id');

        $this->actingAs($user)->putJson("/api/rab-categories/{$cat->id}/items/{$itemId}", ['description' => 'Item 1 updated'])
            ->assertStatus(200)->assertJsonFragment(['description' => 'Item 1 updated']);

        $this->actingAs($user)->deleteJson("/api/rab-categories/{$cat->id}/items/{$itemId}")
            ->assertStatus(204);
    }
}
