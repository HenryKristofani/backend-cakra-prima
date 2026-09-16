<?php

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\StockBalance;
use Illuminate\Http\Request;
use App\Http\Controllers\StockTransferController;
use App\Http\Requests\StoreStockTransferRequest;

// 1. Setup Data
$user = User::firstOrCreate(
    ['email' => 'admin@inventory.test'],
    ['name' => 'Admin Test', 'password' => bcrypt('password')]
);
auth()->login($user);

$warehouse = Warehouse::firstOrCreate(
    ['name' => 'Gudang Utama Pusat'],
    ['type' => 'main', 'is_active' => true]
);

$item = Item::firstOrCreate(
    ['name' => 'Semen Tiga Roda'],
    ['unit' => 'zak', 'is_active' => true]
);

echo "Initial Setup:\n";
echo "- User ID: {$user->id}\n";
echo "- Warehouse ID: {$warehouse->id}\n";
echo "- Item ID: {$item->id}\n\n";

// 2. Prepare Request Data
$payload = [
    'type' => 'in',
    'source_type' => 'purchase',
    'destination_warehouse_id' => $warehouse->id,
    'notes' => 'Pembelian semen perdana 500 zak',
    'items' => [
        [
            'item_id' => $item->id,
            'quantity' => 500,
            'unit_price' => 50000
        ]
    ]
];

// 3. Simulate Request directly to Service 
// (or via controller if we instantiate request)
$request = new StoreStockTransferRequest();
$request->merge($payload);
$request->setContainer(app());
$request->setRedirector(app(\Illuminate\Routing\Redirector::class));
$request->validateResolved(); // This runs the form request validation

$controller = app()->make(StockTransferController::class);
$response = $controller->store($request);

echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Response Body: " . $response->getContent() . "\n\n";

// 4. Verification
$balances = StockBalance::where('warehouse_id', $warehouse->id)
    ->where('item_id', $item->id)
    ->get();

echo "=== STOCK BALANCES ===\n";
foreach ($balances as $balance) {
    echo "Warehouse: {$balance->warehouse->name} | Item: {$balance->item->name} | Qty: {$balance->quantity_on_hand} | Updated At: {$balance->updated_at}\n";
}

echo "\n=== REFERENCE NUMBER GENERATED ===\n";
$transfer = json_decode($response->getContent())->data;
echo "Reference Number: {$transfer->reference_number}\n";

