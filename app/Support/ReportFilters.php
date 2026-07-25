<?php

namespace App\Support;

use Illuminate\Http\Request;

class ReportFilters
{
    public function __construct(
        public ?int $plantId = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?int $categoryId = null,
        public ?int $productId = null,
        public ?int $vehicleId = null,
        public ?string $search = null,
        public ?string $financialYear = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $range = app(FinancialYearResolver::class)->resolve($request);

        return new self(
            plantId: $request->integer('plant_id') ?: null,
            dateFrom: $request->filled('date_from') ? $request->string('date_from')->toString() : $range->dateFrom,
            dateTo: $request->filled('date_to') ? $request->string('date_to')->toString() : $range->dateTo,
            categoryId: $request->integer('category_id') ?: null,
            productId: $request->integer('product_id') ?: null,
            vehicleId: $request->integer('vehicle_id') ?: null,
            search: $request->filled('search') ? trim($request->string('search')->toString()) : null,
            financialYear: $range->label,
        );
    }

    /** @return array<string, int|string> */
    public function toQueryArray(): array
    {
        return array_filter([
            'plant_id' => $this->plantId,
            'fy' => $this->financialYear !== 'custom' ? $this->financialYear : null,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'category_id' => $this->categoryId,
            'product_id' => $this->productId,
            'vehicle_id' => $this->vehicleId,
            'search' => $this->search,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
