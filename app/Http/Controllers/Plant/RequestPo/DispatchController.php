<?php

namespace App\Http\Controllers\Plant\RequestPo;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Concerns\BelongsToUserPlant;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Support\PermissionSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DispatchController extends Controller
{
    use AuthorizesPermissions, BelongsToUserPlant;

    public function index(Request $request): View
    {
        $isPurchaser = $this->purchaserSeesAllPlants();

        $purchases = Purchase::with(['supplier', 'plant'])
            ->when(! $isPurchaser, fn ($q) => $q->where('plant_id', $this->plantId()))
            ->when($request->get('filter') === 'pending', fn ($q) => $q->where('status', PurchaseStatus::Ordered)->whereNull('dispatched_at'))
            ->when($request->get('filter') === 'dispatched', fn ($q) => $q->whereNotNull('dispatched_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('plant.request-po.dispatch.index', compact('purchases', 'isPurchaser'));
    }

    public function dispatch(Purchase $purchase): RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::PLANT_DISPATCH_ORDERS);
        $this->authorizePlantAccess($purchase);

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
