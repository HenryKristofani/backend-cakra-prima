<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_summary_without_year_or_month_returns_all_period_totals(): void
    {
        Transaction::create([
            'date' => '2025-01-15',
            'company' => 'Vendor A',
            'description' => 'Income January',
            'payment_method' => 'cash',
            'income' => 100000,
            'expense' => 0,
        ]);

        Transaction::create([
            'date' => '2025-02-20',
            'company' => 'Vendor B',
            'description' => 'Expense February',
            'payment_method' => 'rek',
            'income' => 0,
            'expense' => 50000,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/transactions-summary');

        $response->assertStatus(200)
            ->assertJson([ 
                'total_saldo_kas' => 50000.0,
                'pemasukan_bulan_ini' => 100000.0,
                'pengeluaran_bulan_ini' => 50000.0,
                'total_saldo_cash' => 100000.0,
            ]);
    }

    public function test_summary_with_year_and_month_filters_period_totals(): void
    {
        Transaction::create([
            'date' => '2025-01-15',
            'company' => 'Vendor A',
            'description' => 'Income January',
            'payment_method' => 'cash',
            'income' => 100000,
            'expense' => 0,
        ]);

        Transaction::create([
            'date' => '2025-02-20',
            'company' => 'Vendor B',
            'description' => 'Expense February',
            'payment_method' => 'cash',
            'income' => 0,
            'expense' => 50000,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/transactions-summary?year=2025&month=02');

        $response->assertStatus(200)
            ->assertJson([ 
                'total_saldo_kas' => 50000,
                'pemasukan_bulan_ini' => 0,
                'pengeluaran_bulan_ini' => 50000,
                'total_saldo_cash' => 50000,
            ]);
    }

    public function test_summary_with_project_id_applies_project_filter_only(): void
    {
        $projectA = Project::create(['name' => 'Project A', 'status' => 'aktif']);
        $projectB = Project::create(['name' => 'Project B', 'status' => 'aktif']);

        Transaction::create([
            'date' => '2025-03-10',
            'company' => 'Vendor C',
            'description' => 'Project A income',
            'payment_method' => 'cash',
            'income' => 200000,
            'expense' => 0,
            'project_id' => $projectA->id,
        ]);

        Transaction::create([
            'date' => '2025-03-12',
            'company' => 'Vendor D',
            'description' => 'Project B expense',
            'payment_method' => 'cash',
            'income' => 0,
            'expense' => 150000,
            'project_id' => $projectB->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/transactions-summary?project_id={$projectA->id}");

        $response->assertStatus(200)
            ->assertJson([ 
                'total_saldo_kas' => 200000.0,
                'pemasukan_bulan_ini' => 200000.0,
                'pengeluaran_bulan_ini' => 0.0,
                'total_saldo_cash' => 200000.0,
            ]);
    }
}
