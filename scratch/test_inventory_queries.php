<?php

use App\Models\Warehouse;
use Illuminate\Http\Request;
use App\Http\Controllers\InventoryQueryController;

$controller = app()->make(InventoryQueryController::class);

echo "========================================\n";
echo "1. GET /inventory/stock-balances (All Aggregated)\n";
echo "========================================\n";
$req1 = Request::create('/inventory/stock-balances', 'GET');
$res1 = $controller->stockBalances($req1);
echo json_encode(json_decode($res1->getContent()), JSON_PRETTY_PRINT) . "\n\n";

echo "========================================\n";
echo "2. GET /inventory/stock-balances?item_id=1 (Breakdown per item)\n";
echo "========================================\n";
$req2 = Request::create('/inventory/stock-balances', 'GET', ['item_id' => 1]);
$res2 = $controller->stockBalances($req2);
echo json_encode(json_decode($res2->getContent()), JSON_PRETTY_PRINT) . "\n\n";

echo "========================================\n";
echo "3. GET /inventory/stock-balances?warehouse_id=1 (All items in Gudang Utama)\n";
echo "========================================\n";
$req3 = Request::create('/inventory/stock-balances', 'GET', ['warehouse_id' => 1]);
$res3 = $controller->stockBalances($req3);
echo json_encode(json_decode($res3->getContent()), JSON_PRETTY_PRINT) . "\n\n";

echo "========================================\n";
echo "4. GET /inventory/stock-moves (History)\n";
echo "========================================\n";
$req4 = Request::create('/inventory/stock-moves', 'GET');
$res4 = $controller->stockMoves($req4);
echo json_encode(json_decode($res4->getContent()), JSON_PRETTY_PRINT) . "\n\n";

echo "========================================\n";
echo "5. GET /projects/{project}/stock-report\n";
echo "========================================\n";
// Ambil ID project dari Gudang Project
$projectWarehouse = Warehouse::where('type', 'project')->first();
if ($projectWarehouse && $projectWarehouse->project_id) {
    $projectId = $projectWarehouse->project_id;
    $res5 = $controller->projectStockReport($projectId);
    echo "Project ID: {$projectId}\n";
    echo json_encode(json_decode($res5->getContent()), JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "No project warehouse found for testing.\n";
}
