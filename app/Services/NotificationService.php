<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Notifications\ErpDatabaseNotification;
use App\Support\RelativeUrl;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class NotificationService
{
    public function notifyProductRequestSubmitted(ProductRequest $productRequest): void
    {
        $productRequest->loadMissing(['site', 'requester']);
        $siteId = $productRequest->site_id;
        $siteName = $productRequest->site->name;
        $requesterName = $productRequest->requester?->name ?? 'Site In-charge';

        $this->notifyHoDataOperators(new ErpDatabaseNotification(
            type: 'product_request_new',
            title: 'New product request',
            body: "Product request #{$productRequest->id} from {$siteName} ({$requesterName}).",
            url: RelativeUrl::route('ho.product-requests.show', $productRequest),
            siteId: $siteId,
            requestId: $productRequest->id,
        ));

        $this->notifySitePurchasers($siteId, new ErpDatabaseNotification(
            type: 'product_request_new',
            title: 'New product request',
            body: "Product request #{$productRequest->id} from {$siteName} ({$requesterName}).",
            url: RelativeUrl::route('site.product-requests.show', $productRequest),
            siteId: $siteId,
            requestId: $productRequest->id,
        ));
    }
    public function notifySitePurchasers(int $siteId, Notification $notification): void
    {
        $this->notifyUsers(
            User::query()
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_PURCHASER))
                ->get(),
            $notification
        );
    }
    public function notifyUser(User $user, Notification $notification): void
    {
        if ($user->is_active && $user->isApproved()) {
            $user->notify($notification);
        }
    }

    public function notifySiteInCharge(int $siteId, Notification $notification): void
    {
        $this->notifyUsers(
            User::query()
                ->where('site_id', $siteId)
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_SITE_IN_CHARGE))
                ->get(),
            $notification
        );
    }

    public function notifySiteStaff(int $siteId, Notification $notification, ?int $excludeUserId = null): void
    {
        $users = User::query()
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->whereHas('role', fn ($q) => $q->whereIn('slug', [
                Role::SLUG_SITE_IN_CHARGE,
                Role::SLUG_PURCHASER,
            ]))
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->get();

        $this->notifyUsers($users, $notification);
    }

    public function notifySiteStockUpdated(
        int $siteId,
        int $productId,
        StockMovementType $type,
        float $quantity,
        float $newBalance,
        int $performedBy,
        ?string $notes = null,
    ): void {
        $site = Site::find($siteId);
        $product = Product::with('unit')->find($productId);
        $performer = User::find($performedBy);

        if (! $site || ! $product) {
            return;
        }

        $direction = $type === StockMovementType::In ? 'increased' : 'decreased';
        $sign = $type === StockMovementType::In ? '+' : '−';
        $unit = $product->unit?->abbreviation;
        $qtyLabel = rtrim(rtrim(number_format($quantity, 2), '0'), '.').($unit ? " {$unit}" : '');
        $balanceLabel = rtrim(rtrim(number_format($newBalance, 2), '0'), '.').($unit ? " {$unit}" : '');

        $body = "{$product->name}: {$sign}{$qtyLabel} (balance: {$balanceLabel})";
        if ($performer) {
            $body .= " — {$performer->name}";
        }
        if ($notes) {
            $body .= " · {$notes}";
        }

        $this->notifySiteStaff($siteId, new ErpDatabaseNotification(
            type: 'stock_updated',
            title: "Stock {$direction} at {$site->name}",
            body: $body,
            url: RelativeUrl::route('site.stocks.index', ['product_id' => $productId]),
            siteId: $siteId,
        ), excludeUserId: $performedBy);
    }

    public function notifySuperAdmins(Notification $notification): void
    {
        $this->notifyUsers(
            User::query()
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_SUPER_ADMIN))
                ->get(),
            $notification
        );
    }

    public function notifyHoDataOperators(Notification $notification): void
    {
        $this->notifyUsers(
            User::query()
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->whereHas('role', fn ($q) => $q->whereIn('slug', [
                    Role::SLUG_HO_DATA_OPERATOR,
                    Role::SLUG_SUPER_ADMIN,
                ]))
                ->get(),
            $notification
        );
    }

    public function notifyPurchaseRequestSubmitted(PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->loadMissing(['site', 'requester']);
        $siteId = $purchaseRequest->site_id;
        $siteName = $purchaseRequest->site->name;
        $requesterName = $purchaseRequest->requester?->name ?? 'Purchaser';

        $this->notifyHoDataOperators(new ErpDatabaseNotification(
            type: 'purchase_request_new',
            title: 'New purchase request',
            body: "Purchase request #{$purchaseRequest->id} from {$siteName} ({$requesterName}).",
            url: RelativeUrl::route('ho.purchase-list.index'),
            siteId: $siteId,
            requestId: $purchaseRequest->id,
        ));
    }

    /**
     * @param  Collection<int, User>|array<User>  $users
     */
    public function notifyUsers(Collection|array $users, Notification $notification): void
    {
        foreach ($users as $user) {
            $this->notifyUser($user, $notification);
        }
    }
}
