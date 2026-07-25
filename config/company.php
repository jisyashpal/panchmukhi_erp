<?php

return [
    'name' => env('COMPANY_NAME', 'Panchmukhi'),
    'address' => env('COMPANY_ADDRESS', ''),
    'gstin' => env('COMPANY_GSTIN', ''),
    'financial_year_start_month' => 4,
    'financial_year_history' => 5,
    'purchase_terms' => env('COMPANY_PURCHASE_TERMS', implode("\n", [
        '1. Goods to be supplied as per agreed specifications, quantity and delivery schedule.',
        '2. Payment will be made as per mutually agreed terms after receipt and verification of goods.',
        '3. Damaged, defective or non-conforming material is liable for rejection / replacement.',
        '4. Supplier must mention PO number on invoice, delivery challan and packing list.',
        '5. Prices are firm unless otherwise agreed in writing.',
    ])),
];
