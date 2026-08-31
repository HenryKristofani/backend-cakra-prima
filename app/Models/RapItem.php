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
     * resolvedUnitPrice
     *
     * Satu-satunya sumber kebenaran untuk harga dasar RAP.
     * - Item RAB-sourced: harga dikalkulasi on-the-fly (sudah dipotong pajak) jika relasi tersedia, 
     *   atau mengambil raw DB jika relasi tidak ada.
     * - Item Manual: selalu mengambil nilai raw DB (belum dipotong pajak).
     */
    protected function resolvedUnitPrice(): string
    {
        $rawPrice = (string) $this->getRawOriginal('unit_price');

        if ($this->source_rab_item_id) {
            if ($this->relationLoaded('sourceRabItem') && $this->sourceRabItem) {
                $projectId = $this->category ? $this->category->project_id : null;
                if ($projectId) {
                    $pajakPct = \App\Models\RapSetting::resolvePajak($projectId);
                    $factor   = bcsub('1', bcdiv((string) $pajakPct, '100', 12), 12);
                    return bcmul((string) $this->sourceRabItem->unit_price, $factor, 12);
                }
            }
            return $rawPrice;
        }

        return $rawPrice;
    }

    /**
     * getUnitPriceAttribute
     */
    public function getUnitPriceAttribute($value)
    {
        return (float) $this->resolvedUnitPrice();
    }

    /**
     * effective_unit_price
     */
    public function getEffectiveUnitPriceAttribute(): float
    {
        $price = $this->resolvedUnitPrice();

        if ($this->source_rab_item_id) {
            // Untuk RAB-sourced, resolvedUnitPrice sudah dalam bentuk harga efektif (setelah pajak)
            return (float) $price;
        }

        // Untuk manual items: potong pajak
        $projectId = $this->category ? $this->category->project_id : null;
        if (!$projectId) {
            return (float) $price;
        }

        $pajak = \App\Models\RapSetting::resolvePajak($projectId);
        return (float) $price * (1 - $pajak / 100);
    }

    /**
     * total_price = volume × effective_unit_price (bcmath for full precision)
     */
    public function getTotalPriceAttribute(): string
    {
        $volume = (string) $this->volume;
        $price  = $this->resolvedUnitPrice();

        if (!$this->source_rab_item_id) {
            $projectId = $this->category ? $this->category->project_id : null;
            if ($projectId) {
                $pajak  = \App\Models\RapSetting::resolvePajak($projectId);
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
