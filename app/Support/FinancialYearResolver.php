<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FinancialYearResolver
{
    public const SESSION_KEY = 'ho_financial_year';

    public function resolve(Request $request): FinancialYearRange
    {
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $from = $request->string('date_from')->toString();
            $to = $request->string('date_to')->toString();
            $label = $this->guessLabelFromDates($from, $to);

            return new FinancialYearRange(
                label: $label,
                dateFrom: $from,
                dateTo: $to,
                isManual: true,
            );
        }

        $label = $this->resolveLabel($request);
        $range = FinancialYear::range($label);

        if ($request->filled('fy')) {
            $request->session()->put(self::SESSION_KEY, $label);
        }

        return new FinancialYearRange(
            label: $label,
            dateFrom: $range['from']->toDateString(),
            dateTo: $range['to']->toDateString(),
            isManual: false,
        );
    }

    public function applyToQuery(Builder $query, string $dateColumn, Request $request): FinancialYearRange
    {
        $range = $this->resolve($request);

        if (! $range->isManual) {
            $query->whereDate($dateColumn, '>=', $range->dateFrom)
                ->whereDate($dateColumn, '<=', $range->dateTo);
        } else {
            $query->whereDate($dateColumn, '>=', $range->dateFrom)
                ->whereDate($dateColumn, '<=', $range->dateTo);
        }

        return $range;
    }

    public function ensureSessionDefault(Request $request): string
    {
        $label = $request->session()->get(self::SESSION_KEY);

        if (! is_string($label) || ! FinancialYear::isValidLabel($label)) {
            $label = FinancialYear::currentLabel();
            $request->session()->put(self::SESSION_KEY, $label);
        }

        return $label;
    }

    private function resolveLabel(Request $request): string
    {
        if ($request->filled('fy')) {
            $label = $request->string('fy')->toString();

            if (FinancialYear::isValidLabel($label)) {
                return $label;
            }
        }

        return $this->ensureSessionDefault($request);
    }

    private function guessLabelFromDates(string $from, string $to): string
    {
        try {
            $range = FinancialYear::range(FinancialYear::labelForDate(\Carbon\Carbon::parse($from)));

            if ($range['from']->toDateString() === $from && $range['to']->toDateString() === $to) {
                return FinancialYear::labelForDate(\Carbon\Carbon::parse($from));
            }
        } catch (\Throwable) {
            // Fall through to custom label.
        }

        return 'custom';
    }
}
