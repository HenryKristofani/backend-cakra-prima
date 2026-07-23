<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DebtGroup extends Model
{
    protected $fillable = [
        'name',
        'total_amount',
        'remaining_amount',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(DebtItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    /**
     * Hitung ulang total_amount dan remaining_amount berdasarkan
     * item dan pembayaran yang tercatat. Dipanggil tiap kali
     * item/payment berubah supaya nilainya selalu sinkron.
     */
    public function recalculate(): void
    {
        $totalItems = $this->items()->sum('amount');
        $totalPayments = $this->payments()->sum('amount');

        $this->update([
            'total_amount' => $totalItems,
            'remaining_amount' => $totalItems - $totalPayments,
        ]);
    }
}