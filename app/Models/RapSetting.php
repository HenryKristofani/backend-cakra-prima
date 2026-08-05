<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RapSetting extends Model
{
    protected $fillable = [
        'project_id',
        'pajak_percentage',
    ];

    protected $casts = [
        'pajak_percentage' => 'float',
        'project_id'          => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Resolve the effective pajak% for a given project.
     *
     * Resolution order:
     *   1. Project-specific row (project_id = $projectId)
     *   2. Global default row  (project_id = NULL)
     *   3. 0.0 if neither exists
     */
    public static function resolvePajak(int $projectId): float
    {
        // 1. Project-specific override
        $setting = self::where('project_id', $projectId)->first();

        if ($setting) {
            return (float) $setting->pajak_percentage;
        }

        // 2. Global default (project_id IS NULL)
        $global = self::whereNull('project_id')->first();

        if ($global) {
            return (float) $global->pajak_percentage;
        }

        // 3. No setting found at all
        return 0.0;
    }
}
