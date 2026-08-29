<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RabImportLog extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'original_filename',
        'file_hash',
        'sheet_name',
        'column_mapping',
        'start_row',
        'status',
        'items_imported',
        'items_skipped',
        'items_errored',
        'batch_id',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'column_mapping' => 'array',
        'started_at'     => 'datetime',
        'finished_at'    => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
