<?php

namespace App\Http\Controllers\Ho;

use App\Http\Controllers\Controller;
use App\Support\FinancialYear;
use App\Support\FinancialYearResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FinancialYearController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $label = $request->string('fy')->toString();

        if (! FinancialYear::isValidLabel($label)) {
            return back()->with('error', 'Invalid financial year selected.');
        }

        $request->session()->put(FinancialYearResolver::SESSION_KEY, $label);

        return redirect()->back()->with('success', FinancialYear::displayLabel($label).' selected.');
    }
}
