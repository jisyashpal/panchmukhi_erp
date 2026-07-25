<?php

namespace App\Http\Controllers\Ho\Product;

use App\Http\Controllers\Concerns\ManagesVehicles;
use App\Http\Controllers\Controller;

class VehicleController extends Controller
{
    use ManagesVehicles;

    protected function catalogPortal(): string
    {
        return 'ho';
    }
}
