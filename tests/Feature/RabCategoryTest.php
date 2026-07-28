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
}
