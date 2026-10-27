<?php

namespace App\Services\Marketplace;

use App\Models\Marketplace\Order;

/**
 * Bumps an order's own status from its items' fulfillment_status after a vendor updates one.
 * A multi-vendor order only advances once every vendor's lines have crossed the same threshold.
 */
class OrderStatusRollupService
{
    public function recalculate(Order $order): void
    {
        $statuses = $order->items()->pluck('fulfillment_status');

        if ($statuses->isEmpty()) {
            return;
        }

        if ($statuses->every(fn ($s) => $s === 'delivered')) {
            $this->advanceTo($order, Order::STATUS_DELIVERED);

            return;
        }

        if ($statuses->every(fn ($s) => in_array($s, ['shipped', 'delivered'], true))) {
            $this->advanceTo($order, Order::STATUS_SHIPPED);

            return;
        }

        if ($statuses->contains(fn ($s) => $s !== 'pending')) {
            $this->advanceTo($order, Order::STATUS_PROCESSING);
        }
    }

    /** Never moves an order backwards or out of a terminal state (cancelled/refunded). */
    private function advanceTo(Order $order, string $status): void
    {
        $rank = [Order::STATUS_PAID => 1, Order::STATUS_PROCESSING => 2, Order::STATUS_SHIPPED => 3, Order::STATUS_DELIVERED => 4];

        if (! isset($rank[$order->status]) || ! isset($rank[$status]) || $rank[$status] <= $rank[$order->status]) {
            return;
        }

        $order->update(['status' => $status]);
    }
}
