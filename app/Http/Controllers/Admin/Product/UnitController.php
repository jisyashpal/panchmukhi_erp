<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Concerns\ManagesUnits;
use App\Http\Controllers\Controller;

class UnitController extends Controller
{
    use ManagesUnits;

    protected function catalogPortal(): string
    {
        return 'admin';
    }
}
