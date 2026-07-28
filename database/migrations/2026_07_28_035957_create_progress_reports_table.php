<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rab_item_id')
                  ->constrained('rab_items')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->date('report_date');
            // percentage_complete 0–100; tiap laporan = baris baru (append-only)
            $table->decimal('percentage_complete', 5, 2); // e.g. 75.50
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_reports');
    }
};
