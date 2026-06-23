<?php

namespace App\Http\Controllers\Concerns;

trait HasCatalogPortal
{
    abstract protected function catalogPortal(): string;

    protected function catalogRoute(string $name, mixed $parameters = []): string
    {
        return route($this->catalogPortal().'.'.$name, $parameters);
    }

    protected function catalogView(string $path): string
    {
        return $this->catalogPortal().'.product.'.$path;
    }
}
