<?php

namespace App\Http\Controllers\Ho\Product;

use App\Http\Controllers\Concerns\ManagesCategories;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    use ManagesCategories;

    protected function catalogPortal(): string
    {
        return 'ho';
    }
}
