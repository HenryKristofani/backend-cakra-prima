<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\RapItem;
use App\Models\RapSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fetch items that are sourced from RAB
        $rapItems = RapItem::whereNotNull('source_rab_item_id')
            ->with(['category', 'sourceRabItem'])
            ->get();

        foreach ($rapItems as $item) {
            if ($item->category && $item->sourceRabItem) {
                $pajak = RapSetting::resolvePajak($item->category->project_id);
                $newPrice = (float) $item->sourceRabItem->unit_price * (1 - $pajak / 100);
                
                DB::table('rap_items')
                    ->where('id', $item->id)
                    ->update(['unit_price' => $newPrice]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration since we don't have historical data to revert to
    }
};
