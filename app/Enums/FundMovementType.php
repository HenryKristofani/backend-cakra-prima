<?php

namespace App\Enums;

enum FundMovementType: string
{
    case InitialAllocation = 'InitialAllocation';
    case Transfer = 'Transfer';
    case Withdrawal = 'Withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::InitialAllocation => 'Alokasi Awal',
            self::Transfer => 'Transfer / Realokasi',
            self::Withdrawal => 'Tarik Modal',
        };
    }
}
