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
        // 1. Data Migration
        $items = \Illuminate\Support\Facades\DB::table('items')
            ->select('unit')
            ->whereNotNull('unit')
            ->distinct()
            ->get();

        foreach ($items as $item) {
            $unitName = ucfirst(strtolower($item->unit));
            $unitSymbol = strtolower($item->unit);

            $unitId = \Illuminate\Support\Facades\DB::table('units')->insertGetId([
                'name' => $unitName,
                'symbol' => $unitSymbol,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \Illuminate\Support\Facades\DB::table('items')
                ->where('unit', $item->unit)
                ->update(['unit_id' => $unitId]);
        }

        // 2. Schema Change: Rename column
        Schema::table('items', function (Blueprint $table) {
            $table->renameColumn('unit', 'legacy_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->renameColumn('legacy_unit', 'unit');
        });

        // We don't delete from units because it might be dangerous or unnecessary
    }
};
