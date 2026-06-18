<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Ordered = 'ordered';
    case Partial = 'partial';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
