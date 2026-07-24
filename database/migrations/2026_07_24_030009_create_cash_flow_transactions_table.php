<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flow_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('cash_flow_periods')->cascadeOnDelete();
            $table->date('trans_date');
            $table->string('description');
            $table->string('source'); // CASH | REK
            $table->decimal('out_amount', 15, 2)->default(0);
            $table->decimal('in_amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flow_transactions');
    }
};
