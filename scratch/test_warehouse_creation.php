<?php

// Running inside tinker, so Laravel is already booted

use App\Models\Warehouse;
use App\Models\Project;
use Illuminate\Http\Request;
use App\Http\Controllers\WarehouseController;
use App\Http\Requests\StoreWarehouseRequest;

$project = Project::first();
if (!$project) {
    echo "No project found, creating one...\n";
    $project = Project::create(['name' => 'Project Test Dummy', 'status' => 'aktif']);
}

echo "Testing Create Project Warehouse...\n";

$payload = [
    'name' => 'Gudang Project Test',
    'type' => 'project',
    'project_id' => $project->id,
    'location' => 'Lokasi Test',
    'is_active' => true
];

$request = StoreWarehouseRequest::create('/api/inventory/warehouses', 'POST', $payload);
// Mock the validation manually since we are outside normal request lifecycle
$request->setContainer(app());
$request->setRedirector(app()->make(\Illuminate\Routing\Redirector::class));
$request->validateResolved();

$controller = new WarehouseController();
$response = $controller->store($request);

echo "Response Status: " . $response->getStatusCode() . "\n";
$data = json_decode($response->getContent(), true);
echo "Created Warehouse ID: " . $data['data']['id'] . "\n";
echo "Saved Project ID: " . $data['data']['project_id'] . "\n";
echo "Eager loaded Project Name: " . ($data['data']['project']['name'] ?? 'NULL') . "\n";

// Validate via GET /inventory/warehouses
echo "\nFetching via QueryController...\n";
$queryRequest = Request::create('/api/inventory/warehouses', 'GET', ['type' => 'project']);
$queryController = new \App\Http\Controllers\InventoryQueryController();
$queryResponse = $queryController->getWarehouses($queryRequest);
$queryData = json_decode($queryResponse->getContent(), true)['data'];

$found = null;
foreach ($queryData as $w) {
    if ($w['id'] === $data['data']['id']) {
        $found = $w;
        break;
    }
}

if ($found) {
    echo "Found in GET /warehouses:\n";
    echo "Name: " . $found['name'] . "\n";
    echo "Project ID: " . $found['project_id'] . "\n";
    echo "Project Name: " . ($found['project']['name'] ?? 'NOT LOADED') . "\n";
} else {
    echo "Warehouse not found in query!\n";
}

// Cleanup
Warehouse::find($data['data']['id'])->delete();
echo "\nTest cleanup done.\n";
