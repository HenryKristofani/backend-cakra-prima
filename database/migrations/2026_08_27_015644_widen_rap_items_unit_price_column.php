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
            $table->decimal('unit_price', 20, 6)->change();
        });

        // Recalculate existing items from RAB
        $rapItems = \App\Models\RapItem::whereNotNull('source_rab_item_id')->get();
        foreach ($rapItems as $rapItem) {
            $rabItem = \App\Models\RabItem::find($rapItem->source_rab_item_id);
            if ($rabItem) {
                $projectId = $rabItem->category ? $rabItem->category->project_id : null;
                $pajakPct = $projectId ? \App\Models\RapSetting::resolvePajak($projectId) : 0;
                // Calculate precise unit price without rounding
                $newUnitPrice = (float) $rabItem->unit_price * (1 - $pajakPct / 100);
                
                // Directly update without triggering events that might round it again
                \Illuminate\Support\Facades\DB::table('rap_items')
                    ->where('id', $rapItem->id)
                    ->update(['unit_price' => $newUnitPrice]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rap_items', function (Blueprint $table) {
            $table->decimal('unit_price', 20, 2)->change();
        });
    }
};
