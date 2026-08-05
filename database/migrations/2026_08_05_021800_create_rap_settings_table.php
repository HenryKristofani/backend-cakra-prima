<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rap_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                  ->nullable()
                  ->constrained('projects')
                  ->cascadeOnDelete();
            $table->decimal('potongan_percentage', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rap_settings');
    }
};
