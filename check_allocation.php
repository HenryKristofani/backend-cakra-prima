<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fmt = fn(float $v) => 'Rp ' . number_format($v, 0, ',', '.');

// Find all projects and their is_isolated_cash status
$projects = \App\Models\Project::select('id', 'name', 'is_isolated_cash')->get();
echo "=== All Projects ===\n";
foreach ($projects as $p) {
    echo "  [{$p->id}] {$p->name} — " . ($p->is_isolated_cash ? 'ISOLATED' : 'non-isolated') . "\n";
}
echo "\n";

// For each project, show breakdown by fund source
foreach ($projects as $p) {
    $rows = DB::table('fund_movements')
        ->join('fund_sources', 'fund_movements.fund_source_id', '=', 'fund_sources.id')
        ->where('fund_movements.type', \App\Enums\FundMovementType::InitialAllocation->value)
        ->where('fund_movements.destination_project_id', $p->id)
        ->selectRaw('fund_sources.name as fs_name, SUM(fund_movements.amount) as total')
        ->groupBy('fund_sources.id', 'fund_sources.name')
        ->get();
    
    if ($rows->isEmpty()) continue;
    
    echo "=== Project: {$p->name} (" . ($p->is_isolated_cash ? 'isolated' : 'non-isolated') . ") ===\n";
    foreach ($rows as $row) {
        echo "  {$row->fs_name}: " . $fmt((float)$row->total) . "\n";
    }
    echo "\n";
}

// Global endpoint result (non-isolated only)
echo "=== Global /transactions-summary/by-fund-source (non-isolated only) ===\n";
$global = DB::table('fund_movements')
    ->join('fund_sources', 'fund_movements.fund_source_id', '=', 'fund_sources.id')
    ->join('projects', 'fund_movements.destination_project_id', '=', 'projects.id')
    ->where('fund_movements.type', \App\Enums\FundMovementType::InitialAllocation->value)
    ->where('projects.is_isolated_cash', false)
    ->selectRaw('fund_sources.name as fs_name, SUM(fund_movements.amount) as total')
    ->groupBy('fund_sources.id', 'fund_sources.name')
    ->get();
foreach ($global as $row) {
    echo "  {$row->fs_name}: " . $fmt((float)$row->total) . "\n";
}
echo "\nTotal Saldo Kas (transactions table): " . $fmt(
    (float)\App\Models\Transaction::sum('income') - (float)\App\Models\Transaction::sum('expense')
) . "\n";
