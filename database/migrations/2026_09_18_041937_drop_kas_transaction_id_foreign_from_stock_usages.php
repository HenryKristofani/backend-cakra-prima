<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the foreign key constraint on stock_usages.kas_transaction_id.
     *
     * This column was originally constrained to point only at the `transactions` table.
     * After adding support for isolated-cash projects, kas_transaction_id may also
     * point to a row in `project_kas_transactions`. Dropping the FK turns this column
     * into a plain integer audit-trail field — the referenced table is determined at
     * runtime by checking warehouse->project->is_isolated_cash.
     */
    public function up(): void
    {
        Schema::table('stock_usages', function (Blueprint $table) {
            $table->dropForeign(['kas_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_usages', function (Blueprint $table) {
            $table->foreign('kas_transaction_id')
                  ->references('id')
                  ->on('transactions')
                  ->nullOnDelete();
        });
    }
};
