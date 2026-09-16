<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockUsage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stock_transfer_line_id',
        'warehouse_id',
        'item_id',
        'quantity',
        'usage_note',
        'posted_to_kas',
        'kas_transaction_id',
        'used_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'posted_to_kas' => 'boolean',
        'used_at' => 'datetime',
    ];

    public function stockTransferLine(): BelongsTo
    {
        return $this->belongsTo(StockTransferLine::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function kasTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'kas_transaction_id');
    }
}
