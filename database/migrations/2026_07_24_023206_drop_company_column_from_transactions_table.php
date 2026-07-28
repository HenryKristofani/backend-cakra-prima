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
        // Some database drivers (SQLite in-memory used by tests) do not
        // support dropping columns via ALTER TABLE. Skip the drop when
        // running on SQLite to keep tests fast and avoid errors.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'company')) {
                $table->dropColumn('company');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('company')->nullable();
        });
    }
};