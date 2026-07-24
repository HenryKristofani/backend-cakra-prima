<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Potential extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'description',
        'amount',
        'trans_date',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'trans_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
