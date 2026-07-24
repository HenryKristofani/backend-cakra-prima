<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAdvance extends Model
{
    protected $fillable = [
        'user_id',
        'recipient',
        'description',
        'amount',
        'date_given',
        'date_returned',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date_given' => 'date',
        'date_returned' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
