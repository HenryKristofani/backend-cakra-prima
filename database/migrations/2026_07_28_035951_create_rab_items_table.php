<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained('rab_categories')
                  ->cascadeOnDelete();
            $table->string('description');          // uraian pekerjaan
            $table->decimal('volume', 15, 4);       // qty
            $table->string('unit', 20);             // m2, m3, ls, dll.
            $table->decimal('unit_price', 20, 2);   // harga satuan (Rp)
            $table->integer('sort_order')->default(0);
            // total_price & bobot_percentage TIDAK disimpan — dihitung on-the-fly
            $table->enum('status', ['aktif', 'dibatalkan'])->default('aktif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_items');
    }
};
