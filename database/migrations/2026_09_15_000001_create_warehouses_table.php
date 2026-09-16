<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // enum WarehouseType
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
