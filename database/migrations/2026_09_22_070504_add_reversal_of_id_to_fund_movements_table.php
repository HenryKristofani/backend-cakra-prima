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
        Schema::table('fund_movements', function (Blueprint $table) {
            // Self-referencing FK: "this movement is a reversal of that movement"
            $table->unsignedBigInteger('reversal_of_id')->nullable()->after('created_by');
            $table->foreign('reversal_of_id')
                  ->references('id')
                  ->on('fund_movements')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fund_movements', function (Blueprint $table) {
            $table->dropForeign(['reversal_of_id']);
            $table->dropColumn('reversal_of_id');
        });
    }
};
