<?php

namespace App\Support;

use Illuminate\Support\Str;

class MasterName
{
    public static function normalize(?string $value): string
    {
        $value = str_replace(["\xC2\xA0", "\u{00A0}"], ' ', (string) $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        $value = preg_replace('/[\s.,;:\-\/]+$/u', '', $value);

        return Str::lower(trim((string) $value));
    }

    public static function matches(?string $left, ?string $right): bool
    {
        $left = self::normalize($left);
        $right = self::normalize($right);

        return $left !== '' && $left === $right;
    }

    /**
     * Stronger key for supplier reuse: "KG ELECTRO SOLUTIONS PVT. LTD."
     * and "KG ELECTRO SOLUTIONS PVT LTD" become the same value.
     */
    public static function supplierKey(?string $value): string
    {
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return '';
        }

        $key = str_ireplace(['&', '+'], ' and ', $normalized);
        $key = preg_replace('/[^a-z0-9]+/u', ' ', $key);
        $key = trim((string) preg_replace('/\s+/u', ' ', $key));
        $key = preg_replace('/^(m s|ms|messrs)\b/u', '', $key);
        $key = preg_replace('/\b(private limited|pvt ltd|pvt limited|limited|ltd|inc|llc|corp)\b/u', ' ', $key);
        $key = trim((string) preg_replace('/\s+/u', ' ', $key));

        return $key !== '' ? $key : $normalized;
    }

    public static function matchesSupplier(?string $left, ?string $right): bool
    {
        $left = self::supplierKey($left);
        $right = self::supplierKey($right);

        return $left !== '' && $left === $right;
    }
}
