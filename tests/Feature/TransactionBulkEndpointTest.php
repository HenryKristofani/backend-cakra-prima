<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectKasTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionBulkEndpointTest extends TestCase
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

    public function test_bulk_store_isolated_writes_to_project_kas_transactions_only(): void
    {
        $payload = [
            'items' => [
                ['date' => '2026-08-11', 'company' => 'Cakra Prima', 'description' => 'Beli pasir', 'payment_method' => 'cash', 'expense' => 100000],
                ['date' => '2026-08-11', 'company' => 'Cakra Prima', 'description' => 'Terima DP', 'payment_method' => 'rek', 'income' => 500000],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->isolatedProject->id}/transactions/bulk", $payload);

        $response->assertStatus(201)->assertJsonCount(2);
        $this->assertDatabaseCount('project_kas_transactions', 2);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_bulk_store_normal_writes_to_transactions_only(): void
    {
        $payload = [
            'items' => [
                ['date' => '2026-08-11', 'company' => 'Cakra Prima', 'description' => 'Bayar listrik', 'payment_method' => 'cash', 'expense' => 200000],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->normalProject->id}/transactions/bulk", $payload);

        $response->assertStatus(201)->assertJsonCount(1);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('project_kas_transactions', 0);
    }

    public function test_bulk_store_rollback_all_on_any_validation_failure(): void
    {
        $payload = [
            'items' => [
                ['date' => '2026-08-11', 'company' => 'Cakra Prima', 'description' => 'Valid item', 'payment_method' => 'cash', 'expense' => 50000],
                ['date' => '2026-08-11', 'company' => 'Cakra Prima', 'payment_method' => 'rek', 'income' => 300000],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->normalProject->id}/transactions/bulk", $payload);

        $response->assertStatus(422);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_bulk_update_normal_project(): void
    {
        $trx = Transaction::create([
            'project_id' => $this->normalProject->id,
            'date' => '2026-08-01',
            'company' => 'Cakra Prima',
            'description' => 'Lama',
            'payment_method' => 'cash',
            'expense' => 50000,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/transactions/bulk', [
                'items' => [[
                    'id' => $trx->id,
                    'project_id' => $this->normalProject->id,
                    'description' => 'Baru',
                ]],
            ]);

        $response->assertStatus(200)->assertJsonCount(1);
        $this->assertDatabaseHas('transactions', ['id' => $trx->id, 'description' => 'Baru']);
    }

    public function test_bulk_update_isolated_project(): void
    {
        $trx = ProjectKasTransaction::create([
            'project_id' => $this->isolatedProject->id,
            'date' => '2026-08-01',
            'company' => 'Cakra Prima',
            'description' => 'Lama isolated',
            'payment_method' => 'cash',
            'expense' => 70000,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/transactions/bulk', [
                'items' => [[
                    'id' => $trx->id,
                    'project_id' => $this->isolatedProject->id,
                    'description' => 'Baru isolated',
                ]],
            ]);

        $response->assertStatus(200)->assertJsonCount(1);
        $this->assertDatabaseHas('project_kas_transactions', ['id' => $trx->id, 'description' => 'Baru isolated']);
    }

    public function test_bulk_update_mixed_isolated_and_normal_in_one_request(): void
    {
        $normalTrx = Transaction::create([
            'project_id' => $this->normalProject->id,
            'date' => '2026-08-01',
            'company' => 'Cakra Prima',
            'description' => 'Old normal',
            'payment_method' => 'cash',
            'expense' => 50000,
        ]);

        $isolatedTrx = ProjectKasTransaction::create([
            'project_id' => $this->isolatedProject->id,
            'date' => '2026-08-01',
            'company' => 'Cakra Prima',
            'description' => 'Old isolated',
            'payment_method' => 'cash',
            'expense' => 70000,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/transactions/bulk', [
                'items' => [
                    ['id' => $normalTrx->id, 'project_id' => $this->normalProject->id, 'description' => 'Updated normal'],
                    ['id' => $isolatedTrx->id, 'project_id' => $this->isolatedProject->id, 'description' => 'Updated isolated'],
                ],
            ]);

        $response->assertStatus(200)->assertJsonCount(2);
        $this->assertDatabaseHas('transactions', ['id' => $normalTrx->id, 'description' => 'Updated normal']);
        $this->assertDatabaseHas('project_kas_transactions', ['id' => $isolatedTrx->id, 'description' => 'Updated isolated']);
    }

    public function test_bulk_update_partial_failure_does_not_update_any(): void
    {
        $normalTrx = Transaction::create([
            'project_id' => $this->normalProject->id,
            'date' => '2026-08-01',
            'company' => 'Cakra Prima',
            'description' => 'Old normal',
            'payment_method' => 'cash',
            'expense' => 50000,
        ]);

        $isolatedTrx = ProjectKasTransaction::create([
            'project_id' => $this->isolatedProject->id,
            'date' => '2026-08-01',
            'company' => 'Cakra Prima',
            'description' => 'Old isolated',
            'payment_method' => 'cash',
            'expense' => 70000,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/transactions/bulk', [
                'items' => [
                    ['id' => $normalTrx->id, 'project_id' => $this->normalProject->id, 'description' => 'Updated normal'],
                    ['id' => $isolatedTrx->id, 'project_id' => $this->isolatedProject->id, 'payment_method' => 'invalid'],
                ],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('transactions', ['id' => $normalTrx->id, 'description' => 'Old normal']);
        $this->assertDatabaseHas('project_kas_transactions', ['id' => $isolatedTrx->id, 'payment_method' => 'cash']);
    }
}
