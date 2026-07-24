<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PENTING: jangan langsung jalankan migration ini setelah
// add_project_id_to_transactions_table. Pastikan dulu semua data
// transaksi lama (kolom `company`) sudah dipetakan manual ke
// `project_id` yang sesuai, baru jalankan ini untuk bersih-bersih.

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('company');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('company')->nullable();
        });
    }
};