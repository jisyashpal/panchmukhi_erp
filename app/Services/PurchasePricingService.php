<?php

namespace App\Services;

class PurchasePricingService
{
    /**
     * @return array{taxable_amount: float, cgst_amount: float, sgst_amount: float, igst_amount: float, line_total: float}
     */
    public function calculateLine(float $quantity, float $unitPrice, float $gstRate, bool $isInterstate): array
    {
        $taxable = round($quantity * $unitPrice, 2);
        $gstAmount = round($taxable * $gstRate / 100, 2);

        if ($isInterstate) {
            return [
                'taxable_amount' => $taxable,
                'cgst_amount' => 0,
                'sgst_amount' => 0,
                'igst_amount' => $gstAmount,
                'line_total' => round($taxable + $gstAmount, 2),
            ];
        }

        $half = round($gstAmount / 2, 2);

        return [
            'taxable_amount' => $taxable,
            'cgst_amount' => $half,
            'sgst_amount' => $half,
            'igst_amount' => 0,
            'line_total' => round($taxable + $gstAmount, 2),
        ];
    }

    /**
     * @param  array<int, array{taxable_amount: float, cgst_amount: float, sgst_amount: float, igst_amount: float, line_total: float}>  $lines
     * @return array{subtotal: float, total_cgst: float, total_sgst: float, total_igst: float, total_gst: float, grand_total: float}
     */
    public function sumLines(array $lines): array
    {
        $subtotal = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;

        foreach ($lines as $line) {
            $subtotal += $line['taxable_amount'];
            $totalCgst += $line['cgst_amount'];
            $totalSgst += $line['sgst_amount'];
            $totalIgst += $line['igst_amount'];
        }

        $totalGst = round($totalCgst + $totalSgst + $totalIgst, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'total_cgst' => round($totalCgst, 2),
            'total_sgst' => round($totalSgst, 2),
            'total_igst' => round($totalIgst, 2),
            'total_gst' => $totalGst,
            'grand_total' => round($subtotal + $totalGst, 2),
        ];
    }
}
