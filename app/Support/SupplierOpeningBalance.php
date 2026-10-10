<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class SupplierOpeningBalance
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $hasAmount = fn () => (float) request()->input('opening_balance') > 0;

        return [
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_type' => ['nullable', Rule::requiredIf($hasAmount), Rule::in(['debit', 'credit'])],
            'opening_balance_date' => ['nullable', Rule::requiredIf($hasAmount), 'date'],
        ];
    }

    /** @return array<string, string> */
    public static function attributeNames(): array
    {
        return [
            'opening_balance' => 'opening balance',
            'opening_balance_type' => 'opening balance type',
            'opening_balance_date' => 'as-on date',
        ];
    }

    /**
     * A blank or zero amount clears the opening so it can still be set later.
     *
     * @param  array<string, mixed>  $data
     * @return array{opening_balance: float|null, opening_balance_type: string|null, opening_balance_date: mixed}
     */
    public static function attributes(array $data): array
    {
        $amount = round((float) ($data['opening_balance'] ?? 0), 2);

        if ($amount <= 0) {
            return [
                'opening_balance' => null,
                'opening_balance_type' => null,
                'opening_balance_date' => null,
            ];
        }

        return [
            'opening_balance' => $amount,
            'opening_balance_type' => $data['opening_balance_type'],
            'opening_balance_date' => $data['opening_balance_date'],
        ];
    }
}
