<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'status',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
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