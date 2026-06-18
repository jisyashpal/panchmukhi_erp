<?php

namespace App\Enums;

enum StockReferenceType: string
{
    case Purchase = 'purchase';
    case StockOut = 'stock_out';
    case StockIn = 'stock_in';
    case ProductUse = 'product_use';
    case Adjustment = 'adjustment';
}
