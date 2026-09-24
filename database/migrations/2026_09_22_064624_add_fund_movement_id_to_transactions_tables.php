<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('fund_movement_id')->nullable()->constrained('fund_movements')->nullOnDelete();
        });

        Schema::table('project_kas_transactions', function (Blueprint $table) {
            $table->foreignId('fund_movement_id')->nullable()->constrained('fund_movements')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['fund_movement_id']);
            $table->dropColumn('fund_movement_id');
        });

        Schema::table('project_kas_transactions', function (Blueprint $table) {
            $table->dropForeign(['fund_movement_id']);
            $table->dropColumn('fund_movement_id');
        });
    }
};
