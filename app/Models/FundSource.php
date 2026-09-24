<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\FundSourceType;

class FundSource extends Model
{
    protected $fillable = [
        'name',
        'type',
        'initial_amount',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'type' => FundSourceType::class,
        'initial_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function fundMovements(): HasMany
    {
        return $this->hasMany(FundMovement::class);
    }
}
