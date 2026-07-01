<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Plant;
use App\Models\User;
use App\Notifications\ErpDatabaseNotification;
use App\Support\RelativeUrl;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class NotificationService
{
    public function notifyProductRequestSubmitted(ProductRequest $productRequest): void
    {
        $productRequest->loadMissing(['plant', 'requester']);
        $plantId = $productRequest->plant_id;
        $plantName = $productRequest->plant->name;
        $requesterName = $productRequest->requester?->name ?? 'Plant In-charge';

        $this->notifyHoDataOperators(new ErpDatabaseNotification(
            type: 'product_request_new',
            title: 'New product request',
            body: "Product request #{$productRequest->id} from {$plantName} ({$requesterName}).",
            url: RelativeUrl::route('ho.product-requests.show', $productRequest),
            plantId: $plantId,
            requestId: $productRequest->id,
        ));

        $this->notifyPlantPurchasers($plantId, new ErpDatabaseNotification(
            type: 'product_request_new',
            title: 'New product request',
            body: "Product request #{$productRequest->id} from {$plantName} ({$requesterName}).",
            url: RelativeUrl::route('plant.product-requests.show', $productRequest),
            plantId: $plantId,
            requestId: $productRequest->id,
        ));
    }
    public function notifyPlantPurchasers(int $plantId, Notification $notification): void
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

    public function notifyPlantInCharge(int $plantId, Notification $notification): void
    {
        $this->notifyUsers(
            User::query()
                ->where('plant_id', $plantId)
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_PLANT_IN_CHARGE))
                ->get(),
            $notification
        );
    }

    public function notifyPlantStaff(int $plantId, Notification $notification, ?int $excludeUserId = null): void
    {
        $users = User::query()
            ->where('plant_id', $plantId)
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->whereHas('role', fn ($q) => $q->whereIn('slug', [
                Role::SLUG_PLANT_IN_CHARGE,
                Role::SLUG_PURCHASER,
            ]))
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->get();

        $this->notifyUsers($users, $notification);
    }

    public function notifyPlantStockUpdated(
        int $plantId,
        int $productId,
        StockMovementType $type,
        float $quantity,
        float $newBalance,
        int $performedBy,
        ?string $notes = null,
    ): void {
        $plant = Plant::find($plantId);
        $product = Product::with('unit')->find($productId);
        $performer = User::find($performedBy);

        if (! $plant || ! $product) {
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

        $this->notifyPlantStaff($plantId, new ErpDatabaseNotification(
            type: 'stock_updated',
            title: "Stock {$direction} at {$plant->name}",
            body: $body,
            url: RelativeUrl::route('plant.stocks.index', ['product_id' => $productId]),
            plantId: $plantId,
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
        $purchaseRequest->loadMissing(['plant', 'requester']);
        $plantId = $purchaseRequest->plant_id;
        $plantName = $purchaseRequest->plant->name;
        $requesterName = $purchaseRequest->requester?->name ?? 'Purchaser';

        $this->notifyHoDataOperators(new ErpDatabaseNotification(
            type: 'purchase_request_new',
            title: 'New purchase request',
            body: "Purchase request #{$purchaseRequest->id} from {$plantName} ({$requesterName}).",
            url: RelativeUrl::route('ho.purchase-list.index'),
            plantId: $plantId,
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
