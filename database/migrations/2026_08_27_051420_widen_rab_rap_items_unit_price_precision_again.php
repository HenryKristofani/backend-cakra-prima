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
        Schema::table('rab_items', function (Blueprint $table) {
            $table->decimal('unit_price', 24, 10)->change();
        });

        Schema::table('rap_items', function (Blueprint $table) {
            $table->decimal('unit_price', 24, 10)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rap_items', function (Blueprint $table) {
            $table->decimal('unit_price', 20, 6)->change();
        });

        Schema::table('rab_items', function (Blueprint $table) {
            $table->decimal('unit_price', 20, 2)->change();
        });
    }
};
