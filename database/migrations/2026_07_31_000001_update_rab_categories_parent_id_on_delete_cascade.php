<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rab_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('rab_categories')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rab_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('rab_categories')
                  ->nullOnDelete();
        });
    }
};
