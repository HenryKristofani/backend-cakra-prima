<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flow_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_label');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('classification')->default('SEMUA');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flow_periods');
    }
};
