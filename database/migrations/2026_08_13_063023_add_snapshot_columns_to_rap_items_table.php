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
        Schema::table('rap_items', function (Blueprint $table) {
            $table->string('source_rab_description_snapshot')->nullable()->after('source_rab_item_id');
            $table->decimal('source_rab_volume_snapshot', 15, 2)->nullable()->after('source_rab_description_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rap_items', function (Blueprint $table) {
            $table->dropColumn(['source_rab_description_snapshot', 'source_rab_volume_snapshot']);
        });
    }
};
