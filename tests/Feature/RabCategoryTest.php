<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Project;

class RabCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_list_categories()
    {
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);

        $response = $this->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A',
            'name' => 'Pekerjaan Bongkaran'
        ]);

        $response->assertStatus(201)->assertJsonFragment(['name' => 'Pekerjaan Bongkaran']);

        $list = $this->getJson("/api/projects/{$project->id}/rab-categories");
        $list->assertStatus(200)->assertJsonCount(1);
    }

    public function test_create_nested_category_and_index_returns_tree()
    {
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);

        $parent = $this->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A',
            'name' => 'Parent Category'
        ])->json();

        $child = $this->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A1',
            'name' => 'Child Category',
            'parent_id' => $parent['id'],
        ])->json();

        $list = $this->getJson("/api/projects/{$project->id}/rab-categories");
        $list->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $parent['id'])
            ->assertJsonPath('0.children.0.id', $child['id']);
    }

    public function test_delete_parent_category_cascades_to_children()
    {
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);

        $parent = $this->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A',
            'name' => 'Parent Category'
        ])->json();

        $child = $this->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A1',
            'name' => 'Child Category',
            'parent_id' => $parent['id'],
        ])->json();

        $this->deleteJson("/api/rab-categories/{$parent['id']}")->assertNoContent();

        $this->getJson("/api/projects/{$project->id}/rab-categories")->assertStatus(200)->assertJsonCount(0);
        $this->assertDatabaseMissing('rab_categories', ['id' => $child['id']]);
    }

    public function test_update_category_rejects_direct_circular_reference()
    {
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);

        $parent = $this->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A',
            'name' => 'Parent'
        ])->json();

        $child = $this->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'B',
            'name' => 'Child',
            'parent_id' => $parent['id'],
        ])->json();

        $this->putJson("/api/rab-categories/{$parent['id']}", [
            'parent_id' => $child['id'],
        ])->assertStatus(422);
    }

    public function test_update_category_rejects_transitive_circular_reference()
    {
        $project = Project::create(['name' => 'P1', 'status' => 'aktif']);

        $a = $this->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'A',
            'name' => 'A'
        ])->json();

        $b = $this->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'B',
            'name' => 'B',
            'parent_id' => $a['id'],
        ])->json();

        $c = $this->postJson("/api/projects/{$project->id}/rab-categories", [
            'code' => 'C',
            'name' => 'C',
            'parent_id' => $b['id'],
        ])->json();

        $this->putJson("/api/rab-categories/{$a['id']}", [
            'parent_id' => $c['id'],
        ])->assertStatus(422);
    }
}
