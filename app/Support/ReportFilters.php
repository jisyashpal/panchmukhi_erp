<?php

namespace App\Support;

use Illuminate\Http\Request;

class ReportFilters
{
    public function __construct(
        public ?int $siteId = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?int $categoryId = null,
        public ?int $productId = null,
        public ?string $search = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            siteId: $request->integer('site_id') ?: null,
            dateFrom: $request->filled('date_from') ? $request->string('date_from')->toString() : null,
            dateTo: $request->filled('date_to') ? $request->string('date_to')->toString() : null,
            categoryId: $request->integer('category_id') ?: null,
            productId: $request->integer('product_id') ?: null,
            search: $request->filled('search') ? trim($request->string('search')->toString()) : null,
        );
    }

    /** @return array<string, int|string> */
    public function toQueryArray(): array
    {
        return array_filter([
            'site_id' => $this->siteId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'category_id' => $this->categoryId,
            'product_id' => $this->productId,
            'search' => $this->search,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
