<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_cash_flow_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('cash_flow_periods')->cascadeOnDelete();
            $table->string('section'); // modal_awal | balance_saldo | cashflow | saldo_mengendap | jumlah_saldo
            $table->string('code')->nullable();
            $table->string('label');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_cash_flow_items');
    }
};
