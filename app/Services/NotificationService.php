<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ErpDatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class NotificationService
{
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
            url: route('ho.purchase-list.index'),
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
