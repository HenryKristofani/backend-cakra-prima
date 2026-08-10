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
    ];

    protected $casts = [
        'volume'     => 'float',
        'unit_price' => 'float',
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
     * effective_unit_price = unit_price × (1 - pajak% / 100)
     *
     * Pajak (Pajak & Biaya Admin) is a discount/deduction from the contract price.
     * Formula: Harga RAP = Harga Kontrak dikurangi persentase pajak.
     * Pajak% is resolved from RapSetting for this item's project,
     * with fallback to global default, then to 0.
     */
    public function getEffectiveUnitPriceAttribute(): float
    {
        $projectId = $this->category ? $this->category->project_id : null;
        if (!$projectId) {
            return (float) $this->unit_price;
        }

        $pajak = $projectId
            ? \App\Models\RapSetting::resolvePajak($projectId)
            : 0;

        return round((float) $this->unit_price * (1 - $pajak / 100), 2);
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
