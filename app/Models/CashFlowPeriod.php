<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashFlowPeriod extends Model
{
    protected $fillable = [
        'period_label',
        'start_date',
        'end_date',
        'classification',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OperationalCashFlowItem::class, 'period_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashFlowTransaction::class, 'period_id');
    }
}
