<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$t1 = \App\Models\Transaction::where('description', 'like', '%{"id":%')->get(['id','description']);
$t2 = \App\Models\ProjectKasTransaction::where('description', 'like', '%{"id":%')->get(['id','description']);

echo "=== Bad Transactions in `transactions` ===\n";
foreach ($t1 as $t) {
    echo "ID: {$t->id} - Desc: {$t->description}\n";
}
echo "Count: " . $t1->count() . "\n\n";

echo "=== Bad Transactions in `project_kas_transactions` ===\n";
foreach ($t2 as $t) {
    echo "ID: {$t->id} - Desc: {$t->description}\n";
}
echo "Count: " . $t2->count() . "\n";
