<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\StockTransferType;
use App\Enums\StockTransferSourceType;

class StockTransfer extends Model
{
    public $timestamps = false; // Disable auto updated_at

    protected $fillable = [
        'reference_number',
        'type',
        'source_type',
        'source_warehouse_id',
        'destination_warehouse_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'type' => StockTransferType::class,
        'source_type' => StockTransferSourceType::class,
        'created_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
