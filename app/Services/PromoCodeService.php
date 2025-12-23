<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PromoCodeService
{
    /**
     * Validate a promo code for a given user and price.
     *
     * @param string $code
     * @param User $user
     * @param float $totalPrice
     * @return array
     */
    public function validatePromoCode(string $code, User $user, float $totalPrice)
    {
        $promo = PromoCode::where('code', $code)->first();

        if (!$promo || !$promo->is_active) {
            return ['valid' => false, 'message' => 'Promo code not found or inactive.', 'status' => 404];
        }

        $now = Carbon::now();

        // 1. Check time validity
        if (($promo->valid_from && $promo->valid_from > $now) ||
            ($promo->valid_until && $promo->valid_until < $now)
        ) {
            return ['valid' => false, 'message' => 'Promo code is not valid at this time.', 'status' => 403];
        }

        // 2. Check minimum price
        if ($promo->min_listing_price && $totalPrice < $promo->min_listing_price) {
            return ['valid' => false, 'message' => 'Total price is too low for this promo code.', 'status' => 403];
        }

        // 3. Check global usage limit
        $totalUsages = DB::table('promo_code_usages')
            ->where('promo_code_id', $promo->id)
            ->count();

        if ($promo->usage_limit !== null && $totalUsages >= $promo->usage_limit) {
            return ['valid' => false, 'message' => 'Promo code has reached its global usage limit.', 'status' => 403];
        }

        // 4. Check per-user usage limit
        $userUsages = DB::table('promo_code_usages')
            ->where('promo_code_id', $promo->id)
            ->where('user_id', $user->id)
            ->count();

        if ($userUsages >= $promo->per_user_limit) {
            return ['valid' => false, 'message' => 'You have reached the usage limit for this promo code.', 'status' => 403];
        }

        // Calculate discount
        $discount = 0;
        if ($promo->discount_type === 'percentage') {
            $discount = ($totalPrice * $promo->discount_value) / 100;
            if ($promo->max_discount && $discount > $promo->max_discount) {
                $discount = $promo->max_discount;
            }
        } elseif ($promo->discount_type === 'fixed') {
            $discount = $promo->discount_value;
        }

        $newPrice = max($totalPrice - $discount, 0);

        return [
            'valid' => true,
            'promo' => $promo,
            'old_price' => $totalPrice,
            'new_price' => $newPrice,
            'discount' => $discount,
            'user_usages' => $userUsages,
            'total_usages' => $totalUsages
        ];
    }

    /**
     * Record promo code usage.
     *
     * @param PromoCode $promo
     * @param int $userId
     * @param int|null $listingId
     * @return void
     */
    public function recordUsage(PromoCode $promo, int $userId, int $listingId = null)
    {
        DB::table('promo_code_usages')->insert([
            'promo_code_id' => $promo->id,
            'user_id' => $userId,
            'listing_id' => $listingId,
            'used_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
