<?php

namespace App\Http\Controllers\Marketplace\Concerns;

use App\Models\Marketplace\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Shared between Marketplace\VendorProductController and Marketplace\VendorShippingMethodController:
 * both operate on the logged-in user's own shop and need it to exist and be approved before letting
 * them touch anything.
 */
trait RequiresActiveVendor
{
    /** The logged-in user's shop, only if it can sell. Returns the error JsonResponse otherwise (NO_VENDOR / VENDOR_SUSPENDED / VENDOR_NOT_ACTIVE). */
    private function myActiveVendorOrError(): Vendor|JsonResponse
    {
        $vendor = Vendor::where('user_id', Auth::id())->first();

        if (! $vendor) {
            return response()->json(['message' => 'You do not have a vendor account', 'code' => 'NO_VENDOR'], 404);
        }
        if ($vendor->status === 'suspended') {
            return response()->json(['message' => 'Your shop is suspended', 'code' => 'VENDOR_SUSPENDED', 'reason' => $vendor->status_reason], 403);
        }
        if ($vendor->status !== 'active') {
            return response()->json(['message' => 'Your shop is not approved yet', 'code' => 'VENDOR_NOT_ACTIVE', 'status' => $vendor->status], 403);
        }

        return $vendor;
    }
}
