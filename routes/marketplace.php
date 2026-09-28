<?php

use App\Http\Controllers\Admin\Marketplace\BrandAdminController;
use App\Http\Controllers\Admin\Marketplace\CategoryAdminController;
use App\Http\Controllers\Admin\Marketplace\ShippingProviderAdminController;
use App\Http\Controllers\Admin\Marketplace\VendorAdminController;
use App\Http\Controllers\Admin\Marketplace\VendorShippingMethodAdminController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\Marketplace\BrandController;
use App\Http\Controllers\Marketplace\CategoryController;
use App\Http\Controllers\Marketplace\HomeController;
use App\Http\Controllers\Marketplace\VendorAccountController;
use App\Http\Controllers\Marketplace\VendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketplace routes (required from routes/api.php, so they live under /api)
|--------------------------------------------------------------------------
| Swagger: /marketplace/documentation (storefront), /marketplace-admin/documentation (admin)
*/

// ============================================
// STOREFRONT (mobile app + web)
// Access levels: public = no token, optional = auth.optional, user = auth:api
// ============================================
Route::prefix('marketplace')->group(function () {

    // ---- Home (public) ----
    Route::get('/home', [HomeController::class, 'index']);

    // ---- Step 1: reference data (public) ----
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{idOrSlug}', [CategoryController::class, 'show']);
    Route::get('/brands', [BrandController::class, 'index']);

    // ---- Step 2: vendors (public) ----
    Route::get('/vendors', [VendorController::class, 'index']);
    Route::get('/vendors/{idOrSlug}', [VendorController::class, 'show']);

    // ---- Step 2b: become a vendor (user = auth:api) ----
    Route::middleware('auth:api')->prefix('vendor')->group(function () {
        Route::post('/apply', [VendorAccountController::class, 'apply']);
        Route::get('/me', [VendorAccountController::class, 'me']);
        Route::put('/me', [VendorAccountController::class, 'update']);
    });

    // ---- Images (user = auth:api). Standalone, decoupled upload: see ImageUploadController::uploadMarketplaceImage.
    // Used by every step above: upload here first, then pass the returned URL as logo_path / cover_image_path / image_path.
    Route::middleware('auth:api')->post('/upload-image', [ImageUploadController::class, 'uploadMarketplaceImage']);
});

// ============================================
// ADMIN PANEL (auth.admin, role_id = 1)
// ============================================
Route::prefix('admin/marketplace')->middleware(['auth.admin'])->group(function () {

    // ---- Step 1: reference data ----
    Route::post('/categories/reorder', [CategoryAdminController::class, 'reorder']);
    Route::apiResource('categories', CategoryAdminController::class)->names('admin.marketplace.categories')->whereNumber('category');
    Route::apiResource('brands', BrandAdminController::class)->names('admin.marketplace.brands')->whereNumber('brand');
    Route::apiResource('shipping-providers', ShippingProviderAdminController::class)->names('admin.marketplace.shipping-providers')->whereNumber('shipping_provider');

    // ---- Step 2: vendors ----
    Route::get('/vendors/stats', [VendorAdminController::class, 'stats']);
    Route::apiResource('vendors', VendorAdminController::class)->names('admin.marketplace.vendors')->whereNumber('vendor');
    Route::post('/vendors/{id}/approve', [VendorAdminController::class, 'approve'])->whereNumber('id');
    Route::post('/vendors/{id}/suspend', [VendorAdminController::class, 'suspend'])->whereNumber('id');
    Route::post('/vendors/{id}/reject', [VendorAdminController::class, 'reject'])->whereNumber('id');

    Route::prefix('vendors/{vendorId}/shipping-methods')->whereNumber(['vendorId', 'id'])->group(function () {
        Route::get('/', [VendorShippingMethodAdminController::class, 'index']);
        Route::post('/', [VendorShippingMethodAdminController::class, 'store']);
        Route::get('/{id}', [VendorShippingMethodAdminController::class, 'show']);
        Route::put('/{id}', [VendorShippingMethodAdminController::class, 'update']);
        Route::delete('/{id}', [VendorShippingMethodAdminController::class, 'destroy']);
    });
});
