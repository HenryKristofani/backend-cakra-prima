<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RapSetting extends Model
{
    protected $fillable = [
        'project_id',
        'potongan_percentage',
    ];

    protected $casts = [
        'potongan_percentage' => 'float',
        'project_id'          => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Resolve the effective potongan% for a given project.
     *
     * Resolution order:
     *   1. Project-specific row (project_id = $projectId)
     *   2. Global default row  (project_id = NULL)
     *   3. 0.0 if neither exists
     */
    public static function resolvePotongan(int $projectId): float
    {
        // 1. Project-specific override
        $setting = self::where('project_id', $projectId)->first();

        if ($setting) {
            return (float) $setting->potongan_percentage;
        }

        // 2. Global default (project_id IS NULL)
        $global = self::whereNull('project_id')->first();

        if ($global) {
            return (float) $global->potongan_percentage;
        }

        // 3. No setting found at all
        return 0.0;
    }
}
