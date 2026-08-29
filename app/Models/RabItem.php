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
        'volume'     => 'float',
        // unit_price serialized as string to preserve full DECIMAL(24,10) precision.
        // JS Number is only safe to ~15-17 significant digits; sending as string
        // prevents silent precision loss when the client parses the JSON.
        'unit_price' => 'string',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(RabCategory::class, 'category_id');
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class, 'rab_item_id');
    }

    // Accessor: total_price = volume * unit_price (as string to prevent JS float loss)
    public function getTotalPriceAttribute()
    {
        // Use BC Math for full precision arithmetic
        $v = (string) $this->volume;
        $p = (string) $this->getRawOriginal('unit_price');
        return bcmul($v, $p, 10);
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
