<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectKasTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCashEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $normalProject;
    protected $isolatedProject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        
        $this->normalProject = Project::create([
            'name' => 'Normal Project',
            'is_isolated_cash' => false,
        ]);

        $this->isolatedProject = Project::create([
            'name' => 'Isolated Project',
            'is_isolated_cash' => true,
        ]);
    }

    public function test_normal_project_uses_transactions_table()
    {
        $payload = [
            'project_id' => $this->normalProject->id,
            'date' => '2026-08-01',
            'company' => 'Test',
            'description' => 'Test Normal Kas',
            'payment_method' => 'cash',
            'income' => 1000,
            'expense' => 0,
        ];

        $response = $this->actingAs($this->user)->postJson("/api/projects/{$this->normalProject->id}/transactions", $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('transactions', ['description' => 'Test Normal Kas']);
        $this->assertDatabaseMissing('project_kas_transactions', ['description' => 'Test Normal Kas']);
    }

    public function test_isolated_project_uses_project_kas_transactions_table()
    {
        $payload = [
            'project_id' => $this->isolatedProject->id,
            'date' => '2026-08-02',
            'company' => 'Test',
            'description' => 'Modal Awal Isolated',
            'payment_method' => 'cash',
            'income' => 50000,
            'expense' => 0,
        ];

        $response = $this->actingAs($this->user)->postJson("/api/projects/{$this->isolatedProject->id}/transactions", $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('project_kas_transactions', ['description' => 'Modal Awal Isolated']);
        $this->assertDatabaseMissing('transactions', ['description' => 'Modal Awal Isolated']);
    }

    public function test_isolated_project_does_not_appear_in_global_summary()
    {
        // Add isolated tx
        ProjectKasTransaction::create([
            'project_id' => $this->isolatedProject->id,
            'date' => '2026-08-03',
            'company' => 'Test',
            'description' => 'Isolated Income',
            'payment_method' => 'cash',
            'income' => 100000,
            'expense' => 0,
        ]);

        // Add normal tx
        Transaction::create([
            'project_id' => $this->normalProject->id,
            'date' => '2026-08-03',
            'company' => 'Test',
            'description' => 'Normal Income',
            'payment_method' => 'cash',
            'income' => 20000,
            'expense' => 0,
        ]);

        // Hit global summary (no project_id)
        $response = $this->actingAs($this->user)->getJson("/api/transactions-summary");
        $response->assertStatus(200);

        // Only 20000 should be visible, 100000 is isolated
        $response->assertJson([
            'total_saldo_kas' => 20000,
        ]);
    }
}
