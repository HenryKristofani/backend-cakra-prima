<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressReport extends Model
{
    protected $fillable = [
        'rab_item_id',
        'user_id',
        'report_date',
        'percentage_complete',
        'notes',
    ];

    protected $casts = [
        'report_date' => 'date',
        'percentage_complete' => 'float',
    ];

    public function rabItem(): BelongsTo
    {
        return $this->belongsTo(RabItem::class, 'rab_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
