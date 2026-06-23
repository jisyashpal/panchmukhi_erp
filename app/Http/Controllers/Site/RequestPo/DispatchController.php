<?php

namespace App\Http\Controllers\Site\RequestPo;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Concerns\BelongsToUserSite;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Support\PermissionSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DispatchController extends Controller
{
    use AuthorizesPermissions, BelongsToUserSite;

    public function index(Request $request): View
    {
        $siteId = $this->siteId();

        $purchases = Purchase::with(['supplier', 'site'])
            ->where('site_id', $siteId)
            ->when($request->get('filter') === 'pending', fn ($q) => $q->where('status', PurchaseStatus::Ordered)->whereNull('dispatched_at'))
            ->when($request->get('filter') === 'dispatched', fn ($q) => $q->whereNotNull('dispatched_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('site.request-po.dispatch.index', compact('purchases'));
    }

    public function dispatch(Purchase $purchase): RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::SITE_DISPATCH_ORDERS);
        abort_unless($purchase->site_id === $this->siteId(), 403);

        if ($purchase->dispatched_at) {
            return back()->with('error', 'Order already dispatched.');
        }

        $purchase->update([
            'dispatched_at' => now(),
            'dispatched_by' => auth()->id(),
        ]);

        return back()->with('success', 'Order marked as dispatched.');
    }
}
