<?php

namespace App\Enums;

enum FundSourceType: string
{
    case HutangBank = 'HutangBank';
    case ModalInvestor = 'ModalInvestor';
    case ModalProjectLain = 'ModalProjectLain';
    case Lainnya = 'Lainnya';

    public function label(): string
    {
        return match ($this) {
            self::HutangBank => 'Hutang Bank',
            self::ModalInvestor => 'Modal Investor',
            self::ModalProjectLain => 'Modal dari Project Lain',
            self::Lainnya => 'Lainnya',
        };
    }
}
