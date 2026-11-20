<?php

namespace App\Listeners;

use App\Events\LowStockDetected;
use App\Models\OrganizationMembership;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendLowStockNotification implements ShouldQueue
{
    public int $tries = 3;

    public function handle(LowStockDetected $event): void
    {
        $memberships = OrganizationMembership::where('organization_id', $event->organization_id)
            ->where('active', true)
            ->whereHas('role.permissions', function ($query) {
                $query->where('name', 'reports.read');
            })
            ->with('user')
            ->get();

        foreach ($memberships as $membership) {
            $membership->user?->notify(new LowStockNotification(
                $event->product_name,
                $event->product_sku,
                $event->current_stock,
                $event->minimum_stock,
            ));
        }
    }
}
