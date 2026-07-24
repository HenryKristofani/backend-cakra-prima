<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFlowTransaction extends Model
{
    protected $fillable = [
        'period_id',
        'trans_date',
        'description',
        'source',
        'out_amount',
        'in_amount',
    ];

    protected $casts = [
        'trans_date' => 'date',
        'out_amount' => 'decimal:2',
        'in_amount' => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(CashFlowPeriod::class, 'period_id');
    }
}
