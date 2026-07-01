<?php

namespace App\Support;

use Carbon\Carbon;
use InvalidArgumentException;

class FinancialYear
{
    public static function startMonth(): int
    {
        return (int) config('company.financial_year_start_month', 4);
    }

    public static function historyCount(): int
    {
        return (int) config('company.financial_year_history', 5);
    }

    public static function currentLabel(): string
    {
        return self::labelForDate(now());
    }

    public static function labelForDate(Carbon $date): string
    {
        $startMonth = self::startMonth();
        $year = (int) $date->year;

        if ((int) $date->month < $startMonth) {
            $year--;
        }

        return sprintf('%d-%02d', $year, ($year + 1) % 100);
    }

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public static function range(string $label): array
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $label, $matches)) {
            throw new InvalidArgumentException("Invalid financial year label: {$label}");
        }

        $startYear = (int) $matches[1];
        $endSuffix = (int) $matches[2];
        $expectedEnd = ($startYear + 1) % 100;

        if ($endSuffix !== $expectedEnd) {
            throw new InvalidArgumentException("Invalid financial year label: {$label}");
        }

        $startMonth = self::startMonth();

        return [
            'from' => Carbon::create($startYear, $startMonth, 1)->startOfDay(),
            'to' => Carbon::create($startYear + 1, $startMonth, 1)->subDay()->endOfDay(),
        ];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $current = self::currentLabel();
        [$startYear] = array_map('intval', explode('-', $current));

        $options = [];

        for ($i = 0; $i <= self::historyCount(); $i++) {
            $year = $startYear - $i;
            $label = sprintf('%d-%02d', $year, ($year + 1) % 100);
            $options[$label] = 'FY '.$label;
        }

        return $options;
    }

    public static function displayLabel(string $label): string
    {
        return 'FY '.$label;
    }

    public static function isValidLabel(string $label): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}$/', $label);
    }
}
