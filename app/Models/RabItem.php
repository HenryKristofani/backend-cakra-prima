<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RabItem extends Model
{
    protected $fillable = [
        'category_id',
        'description',
        'volume',
        'unit',
        'unit_price',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'volume' => 'float',
        'unit_price' => 'float',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(RabCategory::class, 'category_id');
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class, 'rab_item_id');
    }

    // Accessor: total_price = volume * unit_price
    public function getTotalPriceAttribute()
    {
        return (float) $this->volume * (float) $this->unit_price;
    }

    // Accessor: latest_progress_percentage (0 if none)
    public function getLatestProgressPercentageAttribute()
    {
        $latest = $this->progressReports()->orderByDesc('report_date')->first();
        if (! $latest) {
            return 0.0;
        }
        return (float) $latest->percentage_complete;
    }
}
