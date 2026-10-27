<?php

namespace App\Services\Marketplace;

use App\Models\CommissionSetting;
use App\Models\Marketplace\Vendor;
use Illuminate\Support\Facades\Log;

/**
 * Precedence frozen at order time (per the marketplace_order_items migration comment):
 * vendor override > category > global. Applied to product revenue only — shipping fees
 * pass through 100% to the vendor and are never run through this resolver.
 */
class CommissionResolverService
{
    /** @return array{rate: float, amount: float, net: float} */
    public function resolve(Vendor $vendor, ?int $categoryId, float $lineTotal): array
    {
        $rate = $vendor->commission_override !== null
            ? (float) $vendor->commission_override
            : $this->categoryOrGlobalRate($categoryId);

        $amount = round($lineTotal * $rate / 100, 2);

        return [
            'rate'   => $rate,
            'amount' => $amount,
            'net'    => round($lineTotal - $amount, 2),
        ];
    }

    private function categoryOrGlobalRate(?int $categoryId): float
    {
        $rate = $categoryId
            ? CommissionSetting::active()->forCategory($categoryId)->value('commission_percentage')
            : null;

        $rate ??= CommissionSetting::active()->global()->value('commission_percentage');

        if ($rate === null) {
            Log::warning('CommissionResolverService: no vendor override, category or global commission setting found — defaulting to 0%', ['category_id' => $categoryId]);

            return 0.0;
        }

        return (float) $rate;
    }
}
