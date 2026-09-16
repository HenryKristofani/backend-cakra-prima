<?php

namespace App\Enums;

enum StockTransferSourceType: string
{
    case Purchase = 'purchase';
    case Warehouse = 'warehouse';
}
