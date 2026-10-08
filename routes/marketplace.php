<?php

use App\Http\Controllers\Admin\Marketplace\BrandAdminController;
use App\Http\Controllers\Admin\Marketplace\CategoryAdminController;
use App\Http\Controllers\Admin\Marketplace\ProductAdminController;
use App\Http\Controllers\Admin\Marketplace\ShippingProviderAdminController;
use App\Http\Controllers\Admin\Marketplace\VendorAdminController;
use App\Http\Controllers\Admin\Marketplace\VendorShippingMethodAdminController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\Marketplace\BrandController;
use App\Http\Controllers\Marketplace\CategoryController;
use App\Http\Controllers\Marketplace\HomeController;
use App\Http\Controllers\Marketplace\ProductController;
use App\Http\Controllers\Marketplace\ProductReviewController;
use App\Http\Controllers\Marketplace\VendorAccountController;
use App\Http\Controllers\Marketplace\VendorController;
use App\Http\Controllers\Marketplace\WishlistController;
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

    // ---- Step 3: catalog (public) ----
    // /filters MUST be registered before the {idOrSlug} wildcard below, or it gets swallowed as a slug.
    Route::get('/products/filters', [ProductController::class, 'filters']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{idOrSlug}', [ProductController::class, 'show']);

    // ---- Step 4: reviews (list = public, write = user auth:api) ----
    Route::get('/products/{idOrSlug}/reviews', [ProductReviewController::class, 'index']);
    Route::middleware('auth:api')->post('/products/{idOrSlug}/reviews', [ProductReviewController::class, 'store']);

    // ---- Step 3: wishlist (user = auth:api) ----
    Route::middleware('auth:api')->prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/{productId}', [WishlistController::class, 'store'])->whereNumber('productId');
        Route::delete('/{productId}', [WishlistController::class, 'destroy'])->whereNumber('productId');
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

    // ---- Step 3: products ----
    Route::get('/products/stats', [ProductAdminController::class, 'stats']);
    Route::apiResource('products', ProductAdminController::class)->names('admin.marketplace.products')->whereNumber('product');

    Route::prefix('vendors/{vendorId}/shipping-methods')->whereNumber(['vendorId', 'id'])->group(function () {
        Route::get('/', [VendorShippingMethodAdminController::class, 'index']);
        Route::post('/', [VendorShippingMethodAdminController::class, 'store']);
        Route::get('/{id}', [VendorShippingMethodAdminController::class, 'show']);
        Route::put('/{id}', [VendorShippingMethodAdminController::class, 'update']);
        Route::delete('/{id}', [VendorShippingMethodAdminController::class, 'destroy']);
    });
});
