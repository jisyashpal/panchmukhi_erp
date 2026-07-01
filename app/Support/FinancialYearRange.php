<?php

namespace App\Support;

class FinancialYearRange
{
    public function __construct(
        public string $label,
        public string $dateFrom,
        public string $dateTo,
        public bool $isManual = false,
    ) {}

    public function displayLabel(): string
    {
        return FinancialYear::displayLabel($this->label);
    }
}
