<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PromoCode;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PromoCodeController extends Controller
{
    public function checkPromo(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'total_price' => 'required|numeric|min:0',
        ]);
    
        $user = Auth::user();
        $now = Carbon::now();
    
        $promo = PromoCode::where('code', $request->code)->first();
    
        if (!$promo || !$promo->is_active) {
            return response()->json(['message' => 'Promo code not found or inactive.'], 404);
        }
    
        // Vérifier la validité temporelle
        if (($promo->valid_from && $promo->valid_from > $now) ||
            ($promo->valid_until && $promo->valid_until < $now)) {
            return response()->json(['message' => 'Promo code is not valid at this time.'], 403);
        }
    
        // Vérifier le minimum de prix requis
        if ($promo->min_listing_price && $request->total_price < $promo->min_listing_price) {
            return response()->json(['message' => 'Total price is too low for this promo code.'], 403);
        }
    
        // Vérifier nombre d'utilisation global
        $totalUsages = DB::table('promo_code_usages')
            ->where('promo_code_id', $promo->id)
            ->count();
    
        if ($promo->usage_limit !== null && $totalUsages >= $promo->usage_limit) {
            return response()->json(['message' => 'Promo code has reached its global usage limit.'], 403);
        }
    
        // Vérifier nombre d'utilisations par user
        $userUsages = DB::table('promo_code_usages')
            ->where('promo_code_id', $promo->id)
            ->where('user_id', $user->id)
            ->count();
    
        if ($userUsages >= $promo->per_user_limit) {
            return response()->json(['message' => 'You have reached the usage limit for this promo code.'], 403);
        }
    
        // Appliquer la réduction
        $oldPrice = $request->total_price;
        $discount = 0;
    
        if ($promo->discount_type === 'percentage') {
            $discount = ($oldPrice * $promo->discount_value) / 100;
            if ($promo->max_discount && $discount > $promo->max_discount) {
                $discount = $promo->max_discount;
            }
        } elseif ($promo->discount_type === 'fixed') {
            $discount = $promo->discount_value;
        }
    
        $newPrice = max($oldPrice - $discount, 0);
    
        return response()->json([
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'discount' => $discount,
            'discount_type' => $promo->discount_type,
            'discount_value' => $promo->discount_value,
            'description' => $promo->description,
            'usage_count' => $userUsages,
            'total_usage_count' => $totalUsages,
        ]);
    }
    
    
}
