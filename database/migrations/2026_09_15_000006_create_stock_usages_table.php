<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_line_id')->constrained('stock_transfer_lines')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('quantity', 15, 4);
            $table->string('usage_note')->nullable();
            $table->boolean('posted_to_kas')->default(false);
            $table->foreignId('kas_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            
            $table->index(['posted_to_kas', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_usages');
    }
};
