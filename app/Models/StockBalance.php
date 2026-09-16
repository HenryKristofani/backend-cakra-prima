<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    public $timestamps = false; // Disable both, manually handle updated_at if needed, but wait: the migration has updated_at. Let's configure it to only use updated_at.
    const CREATED_AT = null; // Tell Laravel there is no created_at

    protected $fillable = [
        'warehouse_id',
        'item_id',
        'quantity_on_hand',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:4',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
