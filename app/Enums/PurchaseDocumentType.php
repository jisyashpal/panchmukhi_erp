<?php

namespace App\Enums;

enum PurchaseDocumentType: string
{
    case Quotation = 'quotation';
    case PurchaseOrder = 'purchase_order';

    public function label(): string
    {
        return match ($this) {
            self::Quotation => 'Quotation',
            self::PurchaseOrder => 'Purchase Order',
        };
    }
}
