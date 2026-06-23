<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Concerns\ManagesProducts;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    use ManagesProducts;

    protected function catalogPortal(): string
    {
        return 'admin';
    }
}
