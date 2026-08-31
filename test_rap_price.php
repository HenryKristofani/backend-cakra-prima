<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$item = \App\Models\RapItem::whereNotNull('source_rab_item_id')->first();
if (!$item) {
    echo "No RAB-sourced RapItem found.\n";
    exit;
}
$item->load('sourceRabItem', 'category');
echo "ID: " . $item->id . "\n";
echo "Raw unit_price: " . $item->getRawOriginal('unit_price') . "\n";
echo "getUnitPriceAttribute: " . $item->unit_price . "\n";
echo "effective_unit_price: " . $item->effective_unit_price . "\n";
echo "total_price: " . $item->total_price . "\n";
