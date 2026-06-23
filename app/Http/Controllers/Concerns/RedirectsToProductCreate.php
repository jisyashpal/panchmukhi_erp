<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsToProductCreate
{
    use HasCatalogPortal;

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
                ->route($this->catalogPortal().'.products.create', $productCreateQuery)
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
