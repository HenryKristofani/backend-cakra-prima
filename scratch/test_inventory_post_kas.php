<?php

use App\Models\User;
use App\Models\StockUsage;
use App\Models\Account;
use App\Http\Controllers\StockTransferController;
use App\Http\Requests\PostToKasRequest;
use Illuminate\Validation\ValidationException;

// 1. Setup Data
$user = User::first(); 
auth()->login($user);

// Create dummy account for testing
$account = Account::firstOrCreate(
    ['name' => 'Kas Project LPSE'],
    ['type' => 'kas', 'is_active' => true] // Adjust fields according to account table schema, this is a basic assumption
);

// Get the usage from Phase 4 (Usage of 50 zak semen)
$usage = StockUsage::where('posted_to_kas', false)->first();

if (!$usage) {
    die("Error: No unposted stock usage found. Did Phase 4 test run properly?\n");
}

echo "Initial Setup for Post to Kas:\n";
echo "- User ID: {$user->id}\n";
echo "- Stock Usage ID: {$usage->id}\n";
echo "- Account ID: {$account->id}\n\n";

// Function to simulate request
function runPostToKasTest($usageId, $payload) {
    try {
        $request = new PostToKasRequest();
        $request->merge($payload);
        $request->setContainer(app());
        $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
        $request->validateResolved();

        $controller = app()->make(StockTransferController::class);
        $usageService = app()->make(\App\Services\StockUsageService::class);
        
        $response = $controller->postToKas($request, $usageId, $usageService);

        echo "Response Status: " . $response->getStatusCode() . "\n";
        echo "Response Body: " . $response->getContent() . "\n\n";
    } catch (ValidationException $e) {
        echo "Validation Failed: " . json_encode($e->errors()) . "\n\n";
    } catch (\Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n\n";
    }
}

// 2. SCENARIO 1: SUCCESSFUL POST
echo "=== SCENARIO 1: POST TO KAS ===\n";
$payloadSuccess = [
    'payment_method' => 'cash',
    'account_id' => $account->id
];
runPostToKasTest($usage->id, $payloadSuccess);

// 3. SCENARIO 2: IDEMPOTENT CHECK (POST AGAIN)
echo "=== SCENARIO 2: POST AGAIN (SHOULD FAIL) ===\n";
runPostToKasTest($usage->id, $payloadSuccess);

// 4. VERIFICATION
echo "=== VERIFICATION IN DATABASE ===\n";
$usage->refresh();
echo "StockUsage posted_to_kas: " . ($usage->posted_to_kas ? 'TRUE' : 'FALSE') . "\n";
echo "StockUsage kas_transaction_id: " . $usage->kas_transaction_id . "\n\n";

$transaction = \App\Models\Transaction::find($usage->kas_transaction_id);
if ($transaction) {
    echo "Transaction ID: {$transaction->id}\n";
    echo "Project ID: {$transaction->project_id}\n";
    echo "Date: {$transaction->date->format('Y-m-d')}\n";
    echo "Description: {$transaction->description}\n";
    echo "Expense Amount: {$transaction->expense}\n";
    echo "Payment Method: {$transaction->payment_method}\n";
} else {
    echo "Transaction not found!\n";
}
