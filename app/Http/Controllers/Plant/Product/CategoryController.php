<?php

namespace App\Http\Controllers\Plant\Product;

use App\Http\Controllers\Concerns\ManagesCategories;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    use ManagesCategories;

    protected function catalogPortal(): string
    {
        return 'plant';
    }
}
