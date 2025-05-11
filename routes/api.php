<?php

use App\Http\Controllers\MotorcycleImportController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardTypeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ListingAuctionController;
use App\Http\Controllers\MotorcycleBrandController;
use App\Http\Controllers\MotorcycleController;
use App\Http\Controllers\MotorcycleFilterController;
use App\Http\Controllers\MotorcycleModelController;
use App\Http\Controllers\MotorcycleTypeController;
use App\Http\Controllers\MotorcycleYearController;
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
    Route::get('/listings/by-category/{category_id}', [ListingController::class, 'getByCategory']);
    Route::get('/listings/by-city/{city_id}', [ListingController::class, 'getByCity']);

    Route::get('/listings/filter', [ListingController::class, 'filter']);
    Route::get('/listings/recent/city/{city_id}', [ListingController::class, 'getLastByCity']);

    Route::get('/listings/{id}', [ListingController::class, 'getById']);
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


});
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
