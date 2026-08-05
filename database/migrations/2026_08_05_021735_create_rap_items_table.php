<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rap_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained('rap_categories')
                  ->cascadeOnDelete();
            $table->string('description');
            $table->decimal('volume', 15, 4);
            $table->string('unit', 20);
            $table->decimal('unit_price', 20, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rap_items');
    }
};
