<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_group_id')->constrained('debt_groups')->cascadeOnDelete();
            $table->unsignedInteger('no')->nullable(); // nomor urut sesuai Excel
            $table->string('description');
            $table->date('trans_date')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_items');
    }
};