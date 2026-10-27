<?php

namespace App\Services\Marketplace;

use App\Exceptions\Marketplace\InvalidPromoCodeException;
use App\Models\Marketplace\Order;
use App\Models\Marketplace\OrderPromoCode;
use App\Models\PromoCode;

class PromoCodeResolverService
{
    private const MAX_CODES = 2;

    public function resolve(array $codes, int $userId, float $cartSubtotal, array $vendorSubtotals): array
    {
        $codes = array_values(array_unique(array_filter(array_map('trim', $codes))));
        if (empty($codes)) {
            return [];
        }
        if (count($codes) > self::MAX_CODES) {
            throw new InvalidPromoCodeException(null, 'You can apply at most one platform promo code and one shop promo code.');
        }

        $resolved = [];
        $usedScopes = [];

        foreach ($codes as $code) {
            $promo = PromoCode::where('code', $code)->first();
            if (! $promo) {
                throw new InvalidPromoCodeException($code, "Promo code \"{$code}\" was not found.");
            }
            if (! in_array($promo->applies_to, ['marketplace', 'all'], true)) {
                throw new InvalidPromoCodeException($code, "Promo code \"{$code}\" is not valid for marketplace orders.");
            }
            if (! $promo->is_active) {
                throw new InvalidPromoCodeException($code, "Promo code \"{$code}\" is no longer active.");
            }
            if ($promo->valid_from && $promo->valid_from->isFuture()) {
                throw new InvalidPromoCodeException($code, "Promo code \"{$code}\" is not active yet.");
            }
            if ($promo->valid_until && $promo->valid_until->isPast()) {
                throw new InvalidPromoCodeException($code, "Promo code \"{$code}\" has expired.");
            }

            $scopeKey = $promo->vendor_id ?? 'platform';
            if (isset($usedScopes[$scopeKey])) {
                throw new InvalidPromoCodeException($code, 'Only one platform promo code and one shop promo code can be applied per order.');
            }

            $scopeSubtotal = $promo->vendor_id ? ($vendorSubtotals[$promo->vendor_id] ?? null) : $cartSubtotal;
            if ($promo->vendor_id && $scopeSubtotal === null) {
                throw new InvalidPromoCodeException($code, "Promo code \"{$code}\" is for a shop that isn't in your cart.");
            }

            if ($promo->min_listing_price && $scopeSubtotal < (float) $promo->min_listing_price) {
                throw new InvalidPromoCodeException($code, "Promo code \"{$code}\" requires a minimum order of {$promo->min_listing_price}.");
            }

            $this->assertUsageAvailable($promo, $userId, $code);

            $discount = $promo->discount_type === 'fixed'
                ? min((float) $promo->discount_value, $scopeSubtotal)
                : round($scopeSubtotal * (float) $promo->discount_value / 100, 2);

            if ($promo->discount_type === 'percentage' && $promo->max_discount) {
                $discount = min($discount, (float) $promo->max_discount);
            }

            $usedScopes[$scopeKey] = true;
            $resolved[] = [
                'promo'          => $promo,
                'vendor_id'      => $promo->vendor_id,
                'scope_subtotal' => $scopeSubtotal,
                'discount'       => round($discount, 2),
            ];
        }

        return $resolved;
    }

    private function assertUsageAvailable(PromoCode $promo, int $userId, string $code): void
    {
        $base = OrderPromoCode::where('promo_code_id', $promo->id)
            ->whereHas('order', fn ($q) => $q->where('status', '!=', Order::STATUS_CANCELLED));

        if ($promo->usage_limit !== null && (clone $base)->count() >= $promo->usage_limit) {
            throw new InvalidPromoCodeException($code, "Promo code \"{$code}\" has reached its usage limit.");
        }

        if ($promo->per_user_limit !== null) {
            $userUsages = (clone $base)->whereHas('order', fn ($q) => $q->where('user_id', $userId))->count();
            if ($userUsages >= $promo->per_user_limit) {
                throw new InvalidPromoCodeException($code, "You've already used promo code \"{$code}\" the maximum number of times.");
            }
        }
    }
}
