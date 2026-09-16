<?php

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockUsage;
use App\Http\Controllers\StockTransferController;
use App\Http\Requests\StoreStockTransferRequest;
use Illuminate\Validation\ValidationException;

// 1. Setup Data
$user = User::first(); 
auth()->login($user);

$warehouseProject = Warehouse::where('type', 'project')->first();
$itemSemen = Item::first();

echo "Initial Setup for Usage:\n";
echo "- User ID: {$user->id}\n";
echo "- Project Warehouse ID: {$warehouseProject->id}\n";
echo "- Item ID (Semen): {$itemSemen->id}\n\n";

// Function to simulate request
function runUsageTest($payload) {
    try {
        $request = new StoreStockTransferRequest();
        $request->merge($payload);
        $request->setContainer(app());
        $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
        $request->validateResolved();

        $controller = app()->make(StockTransferController::class);
        $response = $controller->store($request);

        echo "Response Status: " . $response->getStatusCode() . "\n";
        echo "Response Body: " . $response->getContent() . "\n\n";
    } catch (ValidationException $e) {
        echo "Validation Failed: " . json_encode($e->errors()) . "\n\n";
    } catch (\App\Exceptions\InsufficientStockException $e) {
        $response = $e->render(request());
        echo "InsufficientStockException Caught!\n";
        echo "Response Status: " . $response->getStatusCode() . "\n";
        echo "Response Body: " . $response->getContent() . "\n\n";
    } catch (\Exception $e) {
        echo "Other Exception: " . $e->getMessage() . "\n\n";
    }
}

// 2. SCENARIO 1: SUCCESSFUL USAGE (50 zak)
echo "=== SCENARIO 1: USAGE 50 ZAK ===\n";
$payloadSuccess = [
    'type' => 'usage',
    'warehouse_id' => $warehouseProject->id,
    'notes' => 'Pemakaian semen minggu ini',
    'items' => [
        [
            'item_id' => $itemSemen->id,
            'quantity' => 50,
            'unit_price' => 50000,
            'usage_note' => 'pengecoran pondasi'
        ]
    ]
];
runUsageTest($payloadSuccess);


// 3. Verification of final balances
echo "=== FINAL STOCK BALANCES ===\n";
$balance = StockBalance::where('warehouse_id', $warehouseProject->id)
    ->where('item_id', $itemSemen->id)
    ->first();

echo "Warehouse: {$balance->warehouse->name} | Item: {$balance->item->name} | Qty: {$balance->quantity_on_hand}\n\n";

echo "=== STOCK USAGES CREATED ===\n";
$usages = StockUsage::where('warehouse_id', $warehouseProject->id)->get();
foreach ($usages as $usage) {
    $statusKas = $usage->posted_to_kas ? 'TRUE' : 'FALSE';
    echo "Usage ID: {$usage->id} | Item: {$usage->item->name} | Qty: {$usage->quantity} | Note: {$usage->usage_note} | Posted to Kas: {$statusKas}\n";
}
