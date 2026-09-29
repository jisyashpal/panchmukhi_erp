<?php

namespace App\Enums;

enum StockReferenceType: string
{
    case Purchase = 'purchase';
    case StockOut = 'stock_out';
    case StockIn = 'stock_in';
    case ProductUse = 'product_use';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::StockOut => 'Stock Out',
            self::StockIn => 'Manual stock in',
            self::ProductUse => 'Product use',
            self::Adjustment => 'Adjustment',
        };
    }
}
