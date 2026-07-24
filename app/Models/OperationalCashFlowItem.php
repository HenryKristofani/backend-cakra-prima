<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalCashFlowItem extends Model
{
    protected $fillable = [
        'period_id',
        'section',
        'code',
        'label',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(CashFlowPeriod::class, 'period_id');
    }
}
