<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RapItem extends Model
{
    protected $fillable = [
        'category_id',
        'description',
        'volume',
        'unit',
        'unit_price',
        'sort_order',
        'source_rab_item_id',
        'source_rab_description_snapshot',
        'source_rab_volume_snapshot',
    ];

    protected $casts = [
        'volume'     => 'float',
        // Note: unit_price is NOT cast here because we have a getUnitPriceAttribute accessor
        // that handles the on-the-fly calculation for RAB-sourced items.
    ];

    // ─── Relationships ──────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(RapCategory::class, 'category_id');
    }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Transaction::class, 'rap_item_id');
    }

    public function sourceRabItem()
    {
        return $this->belongsTo(RabItem::class, 'source_rab_item_id');
    }

    // ─── Accessors ───────────────────────────────────────────────────────────────

    /**
     * getUnitPriceAttribute
     * 
     * If this is a RAB-sourced item, override the unit_price returned from the database
     * by calculating it on-the-fly: source_rab_item.unit_price * (1 - pajak% / 100).
     * Requires sourceRabItem and category relations to be loaded.
     */
    public function getUnitPriceAttribute($value)
    {
        if ($this->source_rab_item_id && $this->relationLoaded('sourceRabItem') && $this->sourceRabItem) {
            $projectId = $this->category ? $this->category->project_id : null;
            if ($projectId) {
                $pajakPct = \App\Models\RapSetting::resolvePajak($projectId);
                return (float) $this->sourceRabItem->unit_price * (1 - $pajakPct / 100);
            }
        }
        return (float) $value;
    }

    /**
     * effective_unit_price
     *
     * For RAB-sourced items: tax is already baked into the stored unit_price column.
     * Returning effective_unit_price = stored unit_price (raw DB value) avoids double-deduction.
     *
     * For manual items: apply the standard formula unit_price × (1 - pajak% / 100).
     */
    public function getEffectiveUnitPriceAttribute(): float
    {
        if ($this->source_rab_item_id) {
            // For RAB-sourced items, unit_price in DB is already RAB - pajak%.
            // Return the raw DB value to avoid applying pajak again.
            return round((float) $this->getRawOriginal('unit_price'), 2);
        }

        $projectId = $this->category ? $this->category->project_id : null;
        if (!$projectId) {
            return (float) $this->getRawOriginal('unit_price');
        }

        $pajak = \App\Models\RapSetting::resolvePajak($projectId);

        return round((float) $this->getRawOriginal('unit_price') * (1 - $pajak / 100), 2);
    }

    /**
     * total_price = volume × effective_unit_price
     */
    public function getTotalPriceAttribute(): float
    {
        return (float) $this->volume * $this->effective_unit_price;
    }

    /**
     * total_realisasi = SUM(transactions.expense) WHERE rap_item_id = this->id
     *
     * Uses expense column because realisasi biaya = pengeluaran aktual.
     */
    public function getTotalRealisasiAttribute(): float
    {
        $projectId = $this->category ? $this->category->project_id : null;
        if ($projectId) {
            $project = \App\Models\Project::find($projectId);
            if ($project && $project->is_isolated_cash) {
                return (float) \App\Models\ProjectKasTransaction::where('rap_item_id', $this->id)->sum('expense');
            }
        }
        return (float) $this->transactions()->sum('expense');
    }

    /**
     * selisih_laba_rugi = total_price - total_realisasi
     * Positif = untung (biaya rencana > realisasi)
     * Negatif = rugi  (realisasi melebihi rencana)
     */
    public function getSelisihLabaRugiAttribute(): float
    {
        return $this->total_price - $this->total_realisasi;
    }
}
