<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Concerns\ManagesSubCategories;
use App\Http\Controllers\Controller;

class SubCategoryController extends Controller
{
    use ManagesSubCategories;

    protected function catalogPortal(): string
    {
        return 'admin';
    }
}
