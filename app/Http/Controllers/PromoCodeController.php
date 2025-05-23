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

        // Récupérer le code promo actif
        $promo = PromoCode::where('code', $request->code)
            ->where('is_active', true)
            ->first();

        if (!$promo) {
            return response()->json(['message' => 'Promo code not found or inactive.'], 404);
        }

        // Vérifier la validité temporelle
        $now = Carbon::now();
        if (($promo->valid_from && $promo->valid_from > $now) || 
            ($promo->valid_until && $promo->valid_until < $now)) {
            return response()->json(['message' => 'Promo code is not valid at this time.'], 403);
        }

        // Vérifier si l'utilisateur a déjà utilisé ce code
        $used = DB::table('promo_code_usages')
            ->where('promo_code_id', $promo->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($used) {
            return response()->json(['message' => 'You have already used this promo code.'], 403);
        }

        $oldPrice = $request->total_price;
        $discount = 0;

        if ($promo->discount_type === 'percentage') {
            $discount = ($oldPrice * $promo->discount_value) / 100;
            // max_discount ?
            if ($promo->max_discount && $discount > $promo->max_discount) {
                $discount = $promo->max_discount;
            }
        } else if ($promo->discount_type === 'fixed') {
            $discount = $promo->discount_value;
        }

        // Calcul prix final (ne pas descendre sous 0)
        $newPrice = max($oldPrice - $discount, 0);

        return response()->json([
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'discount_type' => $promo->discount_type,
            'discount_value' => $promo->discount_value,
            'description' => $promo->description,
        ]);
    }
}
