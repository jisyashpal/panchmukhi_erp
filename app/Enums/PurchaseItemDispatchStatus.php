<?php

namespace App\Enums;

enum PurchaseItemDispatchStatus: string
{
    case Dispatched = 'dispatched';
    case NotProvided = 'not_provided';
    case Alternative = 'alternative';

    public function label(): string
    {
        return match ($this) {
            self::Dispatched => 'Dispatched',
            self::NotProvided => 'Not provided by supplier',
            self::Alternative => 'Alternative product',
        };
    }

    public function isReceivable(): bool
    {
        return $this === self::Dispatched || $this === self::Alternative;
    }
}
