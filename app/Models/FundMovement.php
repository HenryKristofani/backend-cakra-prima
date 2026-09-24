<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\FundMovementType;

class FundMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'fund_source_id',
        'type',
        'source_project_id',
        'destination_project_id',
        'amount',
        'reference_number',
        'notes',
        'created_by',
        'created_at',
        'reversal_of_id',
    ];

    protected $casts = [
        'type' => FundMovementType::class,
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class);
    }

    public function sourceProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'source_project_id');
    }

    public function destinationProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'destination_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The movement this movement is reversing. */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(FundMovement::class, 'reversal_of_id');
    }

    /** The reversal movement that cancelled this movement (if any). */
    public function reversedBy(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FundMovement::class, 'reversal_of_id');
    }
}
