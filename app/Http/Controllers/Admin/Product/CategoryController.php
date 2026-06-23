<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Concerns\ManagesCategories;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    use ManagesCategories;

    protected function catalogPortal(): string
    {
        return 'admin';
    }
}
