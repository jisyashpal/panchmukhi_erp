<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsToProductCreate
{
    use HasCatalogPortal;

    /**
     * Product-create fields carried through category / item type / brand / unit create.
     * Prefixed as pc_* so they do not clash with catalog form fields like `name`.
     *
     * @return array<string, string>
     */
    protected function catalogReturnFieldMap(): array
    {
        return [
            'pc_name' => 'name',
            'pc_size' => 'size',
            'pc_sku_code' => 'sku_code',
            'pc_category_id' => 'category_id',
            'pc_sub_category_id' => 'sub_category_id',
            'pc_brand_id' => 'brand_id',
            'pc_unit_id' => 'unit_id',
            'pc_reorder_level' => 'reorder_level',
            'product_request_id' => 'product_request_id',
            'product_request_item_id' => 'product_request_item_id',
        ];
    }

    /**
     * @return array<string, int|string>
     */
    protected function productCreateReturnQuery(Request $request): array
    {
        $query = [];

        foreach ($this->catalogReturnFieldMap() as $from => $to) {
            $value = $request->input($from);
            if ($value !== null && $value !== '') {
                $query[$to] = $value;
            }
        }

        return $query;
    }

    /**
     * @param  array<string, int|string|null>  $productCreateQuery
     */
    protected function redirectAfterCatalogStore(
        Request $request,
        string $successMessage,
        string $indexRoute,
        array $productCreateQuery = [],
    ): RedirectResponse {
        if ($request->input('redirect') === 'products.create') {
            return redirect()
                ->route(
                    $this->catalogPortal().'.products.create',
                    array_merge($this->productCreateReturnQuery($request), $productCreateQuery)
                )
                ->with('success', $successMessage);
        }

        return redirect()->route($indexRoute)->with('success', $successMessage);
    }

    /** @return array{redirect: string} */
    protected static function productCreateRedirectParams(): array
    {
        return ['redirect' => 'products.create'];
    }
}
