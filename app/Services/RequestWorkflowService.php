<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\ProductRequest;
use App\Models\PurchaseRequest;

class RequestWorkflowService
{
    public function advanceProductRequest(ProductRequest $request, RequestStatus $status, ?int $userId = null): void
    {
        $request->update([
            'status' => $status,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'decline_reason' => $status === RequestStatus::Cancelled ? $request->decline_reason : null,
        ]);
    }

    public function advancePurchaseRequest(PurchaseRequest $request, RequestStatus $status, ?int $userId = null): void
    {
        $request->update([
            'status' => $status,
            'plant_status' => $status,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'plant_reviewed_by' => $userId,
            'plant_reviewed_at' => now(),
        ]);
    }

    public function cancelProductRequest(ProductRequest $request, ?int $userId, ?string $reason): void
    {
        $request->update([
            'status' => RequestStatus::Cancelled,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'decline_reason' => $reason,
        ]);
    }

    public function cancelPurchaseRequest(PurchaseRequest $request, ?int $userId, ?string $reason): void
    {
        $request->update([
            'status' => RequestStatus::Cancelled,
            'plant_status' => RequestStatus::Cancelled,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'decline_reason' => $reason,
        ]);
    }
}
