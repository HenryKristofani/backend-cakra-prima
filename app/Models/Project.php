<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Project extends Model
{
    protected $fillable = [
        'name',
        'status',
        'location',
        'rab_date',
        'is_isolated_cash',
    ];

    protected $casts = [
        'rab_date' => 'date',
        'is_isolated_cash' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function projectKasTransactions(): HasMany
    {
        return $this->hasMany(ProjectKasTransaction::class);
    }

    /**
     * Semua RabItem untuk project ini (lintas kategori)
     */
    public function rabItems(): HasManyThrough
    {
        return $this->hasManyThrough(RabItem::class, RabCategory::class, 'project_id', 'category_id');
    }

    /**
     * Total RAB aktif (SUM volume * unit_price) untuk project
     */
    public function getTotalRabAktifAttribute()
    {
        $total = $this->rabItems()
            ->where('status', 'aktif')
            ->get()
            ->sum(function ($item) {
                return (float) $item->volume * (float) $item->unit_price;
            });

        return (float) $total;
    }

    /**
     * Overall progress percentage (weighted by bobot_percentage)
     */
    public function getOverallProgressPercentageAttribute()
    {
        $items = $this->rabItems()->where('status', 'aktif')->get();
        $totalRab = $items->sum(fn($i) => (float) $i->volume * (float) $i->unit_price);

        if ($totalRab <= 0) {
            return 0.0;
        }

        $weighted = 0.0;
        foreach ($items as $item) {
            $totalPrice = (float) $item->volume * (float) $item->unit_price;
            $bobot = ($totalPrice / $totalRab) * 100.0;
            $latestProg = (float) $item->latest_progress_percentage;
            $weighted += ($bobot * $latestProg) / 100.0;
        }

        return $weighted;
    }

    /**
     * Scope untuk dropdown form — cuma project yang masih aktif
     * yang perlu muncul sebagai pilihan input transaksi baru.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'aktif');
    }
}