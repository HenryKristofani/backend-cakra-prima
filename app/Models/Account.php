<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'name',
        'type',
        'initial_balance',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
    ];

    protected $appends = [
        'current_balance',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getCurrentBalanceAttribute(): float
    {
        $income = (float) $this->transactions()->sum('income');
        $expense = (float) $this->transactions()->sum('expense');

        return (float) $this->initial_balance + $income - $expense;
    }
}
