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
        // unit_price serialized as string to preserve full DECIMAL(24,10) precision.
        // JS Number is only safe to ~15-17 significant digits; sending as string
        // prevents silent precision loss when the client parses the JSON.
        // Note: unit_price accessor still handles on-the-fly calculation for RAB-sourced items.
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
            return (float) $this->getRawOriginal('unit_price');
        }

        $projectId = $this->category ? $this->category->project_id : null;
        if (!$projectId) {
            return (float) $this->getRawOriginal('unit_price');
        }

        $pajak = \App\Models\RapSetting::resolvePajak($projectId);

        return (float) $this->getRawOriginal('unit_price') * (1 - $pajak / 100);
    }

    /**
     * total_price = volume × effective_unit_price (bcmath for full precision)
     */
    public function getTotalPriceAttribute(): string
    {
        $volume = (string) $this->volume;
        $price  = (string) $this->getRawOriginal('unit_price');

        // For RAB-sourced items the stored unit_price is already rab_price * (1 - pajak/100)
        // (written by migration/sync), so just multiply volume × stored price.
        // For manual items use the effective unit price accessor.
        if (!$this->source_rab_item_id) {
            $projectId = $this->category ? $this->category->project_id : null;
            if ($projectId) {
                $pajak = \App\Models\RapSetting::resolvePajak($projectId);
                // price * (1 - pajak/100)
                $factor = bcsub('1', bcdiv((string) $pajak, '100', 12), 12);
                $price  = bcmul($price, $factor, 12);
            }
        }

        return bcmul($volume, $price, 10);
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
