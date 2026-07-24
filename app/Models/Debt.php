<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Debt extends Model
{
    protected $fillable = [
        'user_id',
        'person_name',
        'amount',
        'paid_amount',
        'status',
        'trans_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'trans_date' => 'date',
    ];

    protected $appends = [
        'remaining_amount',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRemainingAmountAttribute(): float
    {
        return (float) $this->amount - (float) $this->paid_amount;
    }
}
