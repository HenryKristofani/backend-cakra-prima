<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('file_hash', 32)->comment('MD5 hash of the uploaded file');
            $table->string('sheet_name');
            $table->json('column_mapping');
            $table->integer('start_row');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'force'])
                  ->default('pending')
                  ->comment('force = user overrode duplicate warning');
            $table->integer('items_imported')->default(0);
            $table->integer('items_skipped')->default(0)->comment('Division headers, empty rows, etc.');
            $table->integer('items_errored')->default(0)->comment('Broken formula cells (#REF!, etc.)');
            $table->string('batch_id')->nullable()->comment('Laravel Batch ID for status polling');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'file_hash', 'sheet_name'], 'rab_import_logs_duplicate_check');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_import_logs');
    }
};
