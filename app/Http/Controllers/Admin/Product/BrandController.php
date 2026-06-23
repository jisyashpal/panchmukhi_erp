<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Concerns\ManagesBrands;
use App\Http\Controllers\Controller;

class BrandController extends Controller
{
    use ManagesBrands;

    protected function catalogPortal(): string
    {
        return 'admin';
    }
}
