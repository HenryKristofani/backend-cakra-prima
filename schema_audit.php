<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = ['transactions', 'project_kas_transactions', 'projects', 'accounts'];
foreach ($tables as $table) {
    echo "\n--- TABLE: $table ---\n";
    $columns = Illuminate\Support\Facades\DB::select('SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema = \'public\' AND table_name = ?', [$table]);
    foreach ($columns as $c) {
        echo "{$c->column_name} | {$c->data_type} | Nullable: {$c->is_nullable} | Default: {$c->column_default}\n";
    }
}
