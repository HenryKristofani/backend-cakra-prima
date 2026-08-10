<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Project;
use App\Models\User;

class RabCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_create_and_list_categories()
    {
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);

        $response = $this->actingAs($this->user)->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A',
            'name' => 'Pekerjaan Bongkaran'
        ]);

        $response->assertStatus(201)->assertJsonFragment(['name' => 'Pekerjaan Bongkaran']);

        $list = $this->actingAs($this->user)->getJson("/api/projects/{$project->id}/rab-categories");
        $list->assertStatus(200)->assertJsonCount(1);
    }

    public function test_create_nested_category_and_index_returns_tree()
    {
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);

        $parent = $this->actingAs($this->user)->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A',
            'name' => 'Parent Category'
        ])->json();

        $child = $this->actingAs($this->user)->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A1',
            'name' => 'Child Category',
            'parent_id' => $parent['id'],
        ])->json();

        $list = $this->actingAs($this->user)->getJson("/api/projects/{$project->id}/rab-categories");
        $list->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $parent['id'])
            ->assertJsonPath('0.children.0.id', $child['id']);
    }

    public function test_delete_parent_category_cascades_to_children()
    {
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);

        $parent = $this->actingAs($this->user)->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A',
            'name' => 'Parent Category'
        ])->json();

        $child = $this->actingAs($this->user)->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A1',
            'name' => 'Child Category',
            'parent_id' => $parent['id'],
        ])->json();

        $this->actingAs($this->user)->deleteJson("/api/rab-categories/{$parent['id']}")->assertNoContent();

        $this->actingAs($this->user)->getJson("/api/projects/{$project->id}/rab-categories")->assertStatus(200)->assertJsonCount(0);
        $this->assertDatabaseMissing('rab_categories', ['id' => $child['id']]);
    }

    public function test_update_category_rejects_direct_circular_reference()
    {
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);

        $parent = $this->actingAs($this->user)->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A',
            'name' => 'Parent'
        ])->json();

        $child = $this->actingAs($this->user)->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'B',
            'name' => 'Child',
            'parent_id' => $parent['id'],
        ])->json();

        $this->actingAs($this->user)->putJson("/api/rab-categories/{$parent['id']}", [
            'parent_id' => $child['id'],
        ])->assertStatus(422);
    }

    public function test_update_category_rejects_transitive_circular_reference()
    {
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);

        $a = $this->actingAs($this->user)->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A',
            'name' => 'A'
        ])->json();

        $b = $this->actingAs($this->user)->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'B',
            'name' => 'B',
            'parent_id' => $a['id'],
        ])->json();

        $c = $this->actingAs($this->user)->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'C',
            'name' => 'C',
            'parent_id' => $b['id'],
        ])->json();

        $this->actingAs($this->user)->putJson("/api/rab-categories/{$a['id']}", [
            'parent_id' => $c['id'],
        ])->assertStatus(422);
    }
}
