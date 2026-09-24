<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\FundSource;

$fmt = fn(float $v) => 'Rp ' . number_format($v, 0, ',', '.');

// 1. Get Delanggu project and Modal Delanggu fund source
$project = Project::where('name', 'like', '%Delanggu%')->first();
$fundSource = FundSource::where('name', 'like', '%Delanggu%')->first();

echo "Target Project: {$project->name} (Isolated: " . ($project->is_isolated_cash ? 'Yes' : 'No') . ")\n";
echo "Fund Source: {$fundSource->name} (Initial Amount: " . $fmt((float)$fundSource->initial_amount) . ")\n";

// Pre-state
echo "\n--- PRE-STATE ---\n";
// Breakdown of Delanggu in Modal Delanggu
$preNet = DB::table('fund_movements')
    ->where('fund_source_id', $fundSource->id)
    ->where('destination_project_id', $project->id)
    ->sum('amount') 
    - 
    DB::table('fund_movements')
    ->where('fund_source_id', $fundSource->id)
    ->where('source_project_id', $project->id)
    ->sum('amount');
echo "Delanggu Net Allocation: " . $fmt($preNet) . "\n";

// 2. Do Withdrawal
echo "\n--- DOING WITHDRAWAL 300jt ---\n";
$service = app(\App\Services\FundMovementService::class);
try {
$user = \App\Models\User::first();
    $service->createWithdrawal([
        'fund_source_id' => $fundSource->id,
        'source_project_id' => $project->id,
        'amount' => 300000000,
        'payment_method' => 'rek',
        'source_account_id' => null, // isolated, null is allowed
        'notes' => 'Tarik sebagian 300jt',
    ], $user->id);
    echo "Withdrawal Success!\n";
} catch (\Exception $e) {
    echo "Withdrawal Failed: " . $e->getMessage() . "\n";
}

// 3. Post-state
echo "\n--- POST-STATE ---\n";
// Breakdown of Delanggu in Modal Delanggu
$postNet = DB::table('fund_movements')
    ->where('fund_source_id', $fundSource->id)
    ->where('destination_project_id', $project->id)
    ->sum('amount') 
    - 
    DB::table('fund_movements')
    ->where('fund_source_id', $fundSource->id)
    ->where('source_project_id', $project->id)
    ->sum('amount');
echo "Delanggu Net Allocation: " . $fmt($postNet) . "\n";

$fsUpdated = FundSource::withSum(['fundMovements as total_in' => function ($q) {
        $q->where('type', \App\Enums\FundMovementType::InitialAllocation->value);
    }], 'amount')
    ->withSum(['fundMovements as total_out' => function ($q) {
        $q->where('type', \App\Enums\FundMovementType::Withdrawal->value);
    }], 'amount')
    ->find($fundSource->id);
$totalAllocated = ((float)$fsUpdated->total_in - (float)$fsUpdated->total_out);
$remaining = (float)$fsUpdated->initial_amount - $totalAllocated;
echo "Fund Source Total Allocated: " . $fmt($totalAllocated) . "\n";
echo "Fund Source Remaining: " . $fmt($remaining) . "\n";

echo "\n--- KAS BUKU BESAR SUMMARY CHECK ---\n";
$globalAllocated = \App\Models\FundSource::all()->map(function ($fs) {
    $in = \App\Models\FundMovement::where('fund_source_id', $fs->id)
        ->where('type', \App\Enums\FundMovementType::InitialAllocation->value)
        ->whereHas('destinationProject', fn($q) => $q->where('is_isolated_cash', false))
        ->sum('amount');
    $out = \App\Models\FundMovement::where('fund_source_id', $fs->id)
        ->where('type', \App\Enums\FundMovementType::Withdrawal->value)
        ->whereHas('sourceProject', fn($q) => $q->where('is_isolated_cash', false))
        ->sum('amount');
    return (float)$in - (float)$out;
})->sum();
echo "Kas Buku Besar Total Allocated: " . $fmt($globalAllocated) . " (Seharusnya tetap 300jt karena BNI -> LPSE)\n";
