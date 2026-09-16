<?php

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\StockBalance;
use App\Http\Controllers\StockTransferController;
use App\Http\Requests\StoreStockTransferRequest;
use Illuminate\Validation\ValidationException;

// 1. Setup Data
$user = User::first(); // Assuming admin is already there from Phase 2 test
auth()->login($user);

$warehouseUtama = Warehouse::where('type', 'main')->first();
$itemSemen = Item::first();

$project = \App\Models\Project::firstOrCreate(
    ['name' => 'Project LPSE Test'],
    ['status' => 'aktif']
);

// Create new Project Warehouse
$warehouseProject = Warehouse::firstOrCreate(
    ['name' => 'Gudang Project LPSE'],
    ['type' => 'project', 'is_active' => true, 'project_id' => $project->id]
);

echo "Initial Setup for Transfer:\n";
echo "- User ID: {$user->id}\n";
echo "- Source Warehouse ID (Utama): {$warehouseUtama->id}\n";
echo "- Dest Warehouse ID (Project): {$warehouseProject->id}\n";
echo "- Item ID (Semen): {$itemSemen->id}\n\n";

// Function to simulate request
function runTransferTest($payload) {
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

// 2. SCENARIO 1: SUCCESSFUL TRANSFER (100 zak)
echo "=== SCENARIO 1: TRANSFER 100 ZAK ===\n";
$payloadSuccess = [
    'type' => 'transfer',
    'source_warehouse_id' => $warehouseUtama->id,
    'destination_warehouse_id' => $warehouseProject->id,
    'notes' => 'Transfer semen ke project LPSE',
    'items' => [
        [
            'item_id' => $itemSemen->id,
            'quantity' => 100,
            'unit_price' => 50000
        ]
    ]
];
runTransferTest($payloadSuccess);

// 3. SCENARIO 2: FAILED TRANSFER (1000 zak)
echo "=== SCENARIO 2: TRANSFER 1000 ZAK (SHOULD FAIL) ===\n";
$payloadFail = [
    'type' => 'transfer',
    'source_warehouse_id' => $warehouseUtama->id,
    'destination_warehouse_id' => $warehouseProject->id,
    'notes' => 'Transfer berlebih',
    'items' => [
        [
            'item_id' => $itemSemen->id,
            'quantity' => 1000,
            'unit_price' => 50000
        ]
    ]
];
runTransferTest($payloadFail);


// 4. Verification of final balances
echo "=== FINAL STOCK BALANCES ===\n";
$balances = StockBalance::whereIn('warehouse_id', [$warehouseUtama->id, $warehouseProject->id])
    ->where('item_id', $itemSemen->id)
    ->get();

foreach ($balances as $balance) {
    echo "Warehouse: {$balance->warehouse->name} | Item: {$balance->item->name} | Qty: {$balance->quantity_on_hand}\n";
}
