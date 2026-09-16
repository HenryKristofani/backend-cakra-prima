<?php

namespace App\Enums;

enum StockTransferType: string
{
    case In = 'in';
    case Transfer = 'transfer';
    case Usage = 'usage';
}
