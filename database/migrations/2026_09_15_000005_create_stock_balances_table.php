<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->timestamp('updated_at')->nullable(); // Only updated_at for cache table
            
            $table->unique(['warehouse_id', 'item_id']); // Unique composite
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
