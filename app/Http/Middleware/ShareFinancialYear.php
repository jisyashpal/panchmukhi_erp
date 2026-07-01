<?php

namespace App\Http\Middleware;

use App\Support\FinancialYear;
use App\Support\FinancialYearResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareFinancialYear
{
    public function __construct(
        protected FinancialYearResolver $resolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->resolver->ensureSessionDefault($request);

        $financialYear = $this->resolver->resolve($request);

        View::share('financialYear', $financialYear);
        View::share('financialYearOptions', FinancialYear::options());

        return $next($request);
    }
}
