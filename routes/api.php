<?php

use App\Http\Controllers\MotorcycleImportController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardTypeController;
use App\Http\Controllers\PlateFormatController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BikePartBrandController;
use App\Http\Controllers\BikePartCategoryController;
use App\Http\Controllers\CurrencyExchangeRateController;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\LicensePlateController;
use App\Http\Controllers\ListingAuctionController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MotorcycleBrandController;
use App\Http\Controllers\MotorcycleController;
use App\Http\Controllers\MotorcycleFilterController;
use App\Http\Controllers\MotorcycleModelController;
use App\Http\Controllers\MotorcycleTypeController;
use App\Http\Controllers\MotorcycleYearController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\SoomController;
use App\Http\Controllers\WishlistController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

Route::middleware('auth:api')->group(function () {

    // Auth management
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::put('/user/update', [AuthController::class, 'updateProfile']);
    Route::put('/user/two-factor-toggle', [AuthController::class, 'toggleTwoFactor']);


    // Users
    Route::apiResource('users', UserController::class);

    // Cards
    Route::apiResource('BankCards', CardController::class);

    // Card Types
    Route::apiResource('card-types', CardTypeController::class);

    // Listings CRUD
    // Route::prefix('listings')->controller(ListingAuctionController::class)->group(function () {
    //     Route::get('/', 'listingsIndex');
    //     Route::post('/', 'listingsStore');
    //     Route::get('/{id}', 'listingsShow');
    //     Route::put('/{id}', 'listingsUpdate');
    //     Route::delete('/{id}', 'listingsDestroy');
    //     Route::get('/my', 'myListings');
    // });

    // Create listing with auction
    Route::post('/listing-with-auction', [ListingAuctionController::class, 'store']);

    // Auctions CRUD
    Route::prefix('auctions')->controller(ListingAuctionController::class)->group(function () {
        Route::get('/', 'auctionsIndex');
        Route::post('/', 'auctionsStore');
        Route::get('/{id}', 'auctionsShow');
        Route::put('/{id}', 'auctionsUpdate');
        Route::delete('/{id}', 'auctionsDestroy');
        Route::get('/my', 'myAuctions');
    });

    Route::post('/wishlists', [WishlistController::class, 'store']);
    Route::delete('/wishlists/{listing_id}', [WishlistController::class, 'destroy']);

    Route::get('/listings/country/{country_id}', [ListingController::class, 'getByCountry']);
    Route::get('/listings/by-city/{city_id}', [ListingController::class, 'getByCity']);

    Route::get('/listings/filter', [ListingController::class, 'filter']);
    Route::get('/listings/recent/city/{city_id}', [ListingController::class, 'getLastByCity']);

    Route::get('/listings', [ListingController::class, 'getAll']);

    // Existing admin routes
    Route::apiResource('BankCards', CardController::class);

    // New user-specific routes
    Route::get('my-cards', [CardController::class, 'myCards']);
    Route::post('my-cards', [CardController::class, 'addMyCard']);
    Route::put('my-cards/{id}', [CardController::class, 'editMyCard']);
    Route::delete('my-cards/{id}', [CardController::class, 'deleteMyCard']);

    Route::patch('my-cards/{id}/set-default', [CardController::class, 'setAsDefault']);

    Route::post('/listings', [ListingController::class, 'store']);
    Route::get('/my-listing', [ListingController::class, 'my_listing']);

    Route::get('pricing', [ListingController::class, 'getPriceByModelId']);


    Route::apiResource('currency-rates', CurrencyExchangeRateController::class);

    Route::post('promo/check', [PromoCodeController::class, 'checkPromo']);

    Route::post('/upload-image', [ImageUploadController::class, 'upload']);

    // Créer un SOOM sur un listing (Acheteur)
    Route::post('/listings/{listingId}/soom', [SoomController::class, 'createSoom']);

    // Obtenir tous les SOOMs d'un listing (Public)
    Route::get('/listings/{listingId}/sooms', [SoomController::class, 'getListingSooms']);

    // Obtenir le montant minimum requis pour un SOOM (Public)
    Route::get('/listings/{listingId}/minimum-soom', [SoomController::class, 'getMinimumSoomAmount']);

    // Accepter un SOOM (Vendeur uniquement)
    Route::patch('/submissions/{submissionId}/accept', [SoomController::class, 'acceptSoom']);

    Route::patch('/submissions/{submissionId}/reject', [SoomController::class, 'rejectSoom']);

    // Obtenir tous les SOOMs reçus sur mes listings (Vendeur)
    Route::get('/my-listings-sooms', [SoomController::class, 'getMyListingsSooms']);

    // Obtenir tous mes SOOMs envoyés (Acheteur)
    Route::get('/my-sooms', [SoomController::class, 'getMySooms']);

    // Annuler un SOOM
    Route::delete('submissions/{submissionId}/cancel', [SoomController::class, 'cancelSoom'])->middleware('auth:sanctum');

    // Modifier un SOOM
    Route::put('submissions/{submissionId}/edit', [SoomController::class, 'editSoom'])->middleware('auth:sanctum');

    Route::get('/listings/{listingId}/last-soom', [SoomController::class, 'getLastSoom']);
});
Route::get('/listings/{id}', [ListingController::class, 'getById']);

Route::get('/listings/by-category/{category_id}', [ListingController::class, 'getByCategory']);

Route::apiResource('roles', App\Http\Controllers\RoleController::class);
Route::apiResource('motorcycle-types', MotorcycleTypeController::class);
Route::apiResource('motorcycle-brands', MotorcycleBrandController::class);
Route::apiResource('motorcycle-models', MotorcycleModelController::class);
Route::apiResource('motorcycle-years', MotorcycleYearController::class);
Route::apiResource('motorcycles', MotorcycleController::class);


Route::post('/motorcycles/import', [MotorcycleController::class, 'importMotorcycles']);

Route::get('/motorcycle/filter', [MotorcycleFilterController::class, 'filter']);

Route::get('/motorcycle/brand/{brandId}', [MotorcycleFilterController::class, 'getByBrand']);
Route::get('/motorcycle/year/{yearId}', [MotorcycleFilterController::class, 'getByYear']);

// Route::apiResource('wishlists', WishlistController::class);
Route::post('/motorcycles/import', [MotorcycleImportController::class, 'import']);

Route::get('/locations', [LocationController::class, 'index']);

// Countries CRUD
Route::post('/countries', [LocationController::class, 'storeCountry']);
Route::put('/countries/{id}', [LocationController::class, 'updateCountry']);
Route::delete('/countries/{id}', [LocationController::class, 'destroyCountry']);

// Cities CRUD
Route::post('/cities', [LocationController::class, 'storeCity']);
Route::put('/cities/{id}', [LocationController::class, 'updateCity']);
Route::delete('/cities/{id}', [LocationController::class, 'destroyCity']);

Route::apiResource('bike-part-brands', BikePartBrandController::class);

Route::apiResource('bike-part-categories', BikePartCategoryController::class);

Route::get('/listings/search-by-model', [ListingController::class, 'searchByCategoryAndModel']);


Route::apiResource('currency-rates', CurrencyExchangeRateController::class);

Route::get('/brands/listings-count', [ListingController::class, 'getBrandsWithListingCount']);
Route::get('categories/{categoryId}/price-range', [ListingController::class, 'getPriceRangeByCategory'])
    ->where('categoryId', '[1-3]'); // Limite aux catégories 1, 2, 3
Route::get('/filter/motorcycles', [FilterController::class, 'filterMotorcycles']);
Route::get('/filter/spare-parts', [FilterController::class, 'filterSpareParts']);
Route::get('/filter/license-plates', [FilterController::class, 'filterLicensePlates']);
Route::get('/bike-part-categories', [ListingController::class, 'getBikePartCategoriesWithListingCount']);
Route::get('/bike-part-brands', [ListingController::class, 'getBikePartBrandsWithListingCount']);

Route::post('/plate-formats', [PlateFormatController::class, 'store']);

// routes/api.php
Route::post('/license-plates', [LicensePlateController::class, 'store']);


Route::get('/license-plates/{id}/formatted', [LicensePlateController::class, 'showFormatted']);





// Récupérer les formats de plaques par ville
Route::get('/cities/{cityId}/plate-formats', [LicensePlateController::class, 'getFormatsByCity']);

// Alternative avec plus de détails sur la ville
Route::get('/cities/{cityId}/plate-formats/details', [LicensePlateController::class, 'getFormatsByCityWithDetails']);

// Récupérer tous les formats par pays (groupés par ville)
Route::get('/countries/{countryId}/plate-formats', [LicensePlateController::class, 'getFormatsByCountry']);

// Route existante (pour référence)
Route::get('/license-plates/{id}/formatted', [LicensePlateController::class, 'showFormatted']);

