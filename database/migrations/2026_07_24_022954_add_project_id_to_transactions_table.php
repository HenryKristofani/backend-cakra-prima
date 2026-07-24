<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('account_id')
                ->constrained('projects')->nullOnDelete();
        });

        // Kolom `company` (string bebas ketik) sengaja belum langsung dihapus.
        // Migrasikan datanya ke `projects` + isi `project_id` secara manual
        // dulu, baru drop kolom `company` di migration terpisah setelah
        // yakin semua data lama sudah ter-mapping ke project yang benar.
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};