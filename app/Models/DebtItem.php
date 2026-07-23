<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtItem extends Model
{
    protected $fillable = [
        'debt_group_id',
        'no',
        'description',
        'trans_date',
        'amount',
    ];

    protected $casts = [
        'trans_date' => 'date',
    ];

    public function debtGroup(): BelongsTo
    {
        return $this->belongsTo(DebtGroup::class);
    }
}