<?php
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardTypeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ListingAuctionController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

Route::middleware('auth:api')->group(function () {

    // Auth management
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // Users
    Route::apiResource('users', UserController::class);

    // Cards
    Route::apiResource('BankCards', CardController::class);

    // Card Types
    Route::apiResource('card-types', CardTypeController::class);

    // Listings CRUD
    Route::prefix('listings')->controller(ListingAuctionController::class)->group(function () {
        Route::get('/', 'listingsIndex');
        Route::post('/', 'listingsStore');
        Route::get('/{id}', 'listingsShow');
        Route::put('/{id}', 'listingsUpdate');
        Route::delete('/{id}', 'listingsDestroy');
        Route::get('/my', 'myListings');
    });

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

});
