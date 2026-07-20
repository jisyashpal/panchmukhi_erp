<?php

namespace App\Http\Controllers\Plant\Product;

use App\Http\Controllers\Concerns\ManagesBrands;
use App\Http\Controllers\Controller;

class BrandController extends Controller
{
    use ManagesBrands;

    protected function catalogPortal(): string
    {
        return 'plant';
    }
}
