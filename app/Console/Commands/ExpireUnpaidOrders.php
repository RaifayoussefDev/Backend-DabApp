<?php

namespace App\Console\Commands;

use App\Models\Marketplace\Order;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Cancel marketplace orders still unpaid 30 minutes after creation and release their reserved stock';

    public function handle(): int
    {
        $orderIds = Order::pendingPaymentExpired()->pluck('id');
        $cancelled = 0;

        foreach ($orderIds as $orderId) {
            DB::transaction(function () use ($orderId, &$cancelled) {
                $order = Order::where('id', $orderId)->lockForUpdate()->first();

                if (! $order || $order->status !== Order::STATUS_PENDING_PAYMENT || ! $order->expires_at?->isPast()) {
                    return;
                }

                $items = $order->items()->get();

                $items->whereNull('variant_id')->groupBy('product_id')->each(function ($group, $productId) {
                    Product::where('id', $productId)->lockForUpdate()->first()
                        ?->increment('stock_quantity', $group->sum('quantity'));
                });

                $items->whereNotNull('variant_id')->groupBy('variant_id')->each(function ($group, $variantId) {
                    ProductVariant::where('id', $variantId)->lockForUpdate()->first()
                        ?->increment('stock_quantity', $group->sum('quantity'));
                });

                $order->update(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => 'Payment expired']);
                $order->payments()->where('payment_status', 'pending')->update(['payment_status' => 'failed', 'failure_reason' => 'Order expired before payment completed']);

                $cancelled++;
            });
        }

        $this->info("Expired {$cancelled} unpaid order(s).");
        Log::info('orders:expire-unpaid finished', ['cancelled' => $cancelled]);

        return Command::SUCCESS;
    }
}
