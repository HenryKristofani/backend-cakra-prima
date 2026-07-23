<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtPayment extends Model
{
    protected $fillable = [
        'debt_group_id',
        'description',
        'payment_date',
        'amount',
    ];

    protected $casts = [
        'payment_date' => 'date',
    ];

    public function debtGroup(): BelongsTo
    {
        return $this->belongsTo(DebtGroup::class);
    }
}