<?php

use App\Http\Controllers\MotorcycleComparisonController;
use App\Http\Controllers\MyGarageController;
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
use App\Http\Controllers\FirebaseAuthController;
use App\Http\Controllers\FirebasePhoneAuthController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\LicensePlateController;
use App\Http\Controllers\LicensePlateFilterController;
use App\Http\Controllers\ListingAuctionController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MotorcycleBrandController;
use App\Http\Controllers\MotorcycleController;
use App\Http\Controllers\MotorcycleFilterController;
use App\Http\Controllers\MotorcycleModelController;
use App\Http\Controllers\MotorcycleTypeController;
use App\Http\Controllers\MotorcycleYearController;
use App\Http\Controllers\PaymentHistoryController;
use App\Http\Controllers\PayTabsController;
use App\Http\Controllers\PhonePasswordAuthController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\SoomController;
use App\Http\Controllers\WhatsAppOtpController;
use App\Http\Controllers\WishlistController;

use App\Http\Controllers\GuideCategoryController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\GuideImageController;
use App\Http\Controllers\GuideTagController;
use App\Http\Controllers\GuideCommentController;
use App\Http\Controllers\GuideLikeController;
use App\Http\Controllers\GuideBookmarkController;
use App\Http\Controllers\ListingTypeController;


use App\Http\Controllers\EventCategoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventParticipantController;
use App\Http\Controllers\EventReviewController;
use App\Http\Controllers\EventFavoriteController;
use App\Http\Controllers\EventTicketController;
use App\Http\Controllers\EventActivityController;
use App\Http\Controllers\EventSponsorController;
use App\Http\Controllers\EventContactController;
use App\Http\Controllers\EventFaqController;
use App\Http\Controllers\EventUpdateController;
// use App\Http\Controllers\NewsletterController;
// use App\Http\Controllers\PointOfInterestController;
// use App\Http\Controllers\PoiReviewController;
// use App\Http\Controllers\RouteController;
// use App\Http\Controllers\RouteReviewController;

use App\Http\Controllers\{
    NewsletterController,
    NewsletterCampaignController,
    PointOfInterestController,
    PoiTypeController,
    PoiServiceController,
    PoiReviewController,
    PoiReportController,
    RouteController,
    RouteCategoryController,
    RouteTagController,
    RouteWaypointController,
    RouteReviewController,
    RouteWarningController,
    RouteCompletionController
};

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/refresh', [AuthController::class, 'refresh']);

Route::middleware('auth:api')->group(function () {

    // Auth management
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/my-ads', [ListingController::class, 'getMyAds']); // ✅ NOUVELLE ROUTE

    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::put('/user/update', [AuthController::class, 'updateProfile']);
    Route::put('/user/two-factor-toggle', [AuthController::class, 'toggleTwoFactor']);


    // Users
    // Basic CRUD (apiResource)
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/stats', [UserController::class, 'stats']);
        Route::get('/export', [UserController::class, 'export']);
        Route::post('/search', [UserController::class, 'search']);
        Route::post('/bulk-action', [UserController::class, 'bulkAction']);

        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);

        Route::put('/{id}/activate', [UserController::class, 'activate']);
        Route::put('/{id}/deactivate', [UserController::class, 'deactivate']);
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);

        Route::post('/{id}/two-factor/enable', [UserController::class, 'enableTwoFactor']);
        Route::post('/{id}/two-factor/disable', [UserController::class, 'disableTwoFactor']);

        Route::put('/{id}/last-login', [UserController::class, 'updateLastLogin']);
        Route::put('/{id}/online-status', [UserController::class, 'updateOnlineStatus']);
    });
    // Routes additionnelles
    Route::prefix('users')->group(function () {
        // Gestion de compte
        Route::post('/{id}/activate', [UserController::class, 'activateUser']);
        Route::post('/{id}/deactivate', [UserController::class, 'deactivateUser']);
        Route::post('/{id}/verify', [UserController::class, 'verifyUser']);
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::put('/{id}/change-password', [UserController::class, 'changePassword']);

        // Gestion du profil
        Route::put('/{id}/profile-picture', [UserController::class, 'updateProfilePicture']);
        Route::put('/{id}/last-login', [UserController::class, 'updateLastLogin']);
        Route::put('/{id}/online-status', [UserController::class, 'updateOnlineStatus']);

        // Authentification à deux facteurs
        Route::post('/{id}/two-factor/enable', [UserController::class, 'enableTwoFactor']);
        Route::post('/{id}/two-factor/disable', [UserController::class, 'disableTwoFactor']);

        // Relations utilisateur
        Route::get('/{id}/wishlists', [UserController::class, 'getUserWishlists']);
        Route::get('/{id}/listings', [UserController::class, 'getUserListings']);
        Route::get('/{id}/bank-cards', [UserController::class, 'getUserBankCards']);
        Route::get('/{id}/auction-history', [UserController::class, 'getUserAuctionHistory']);

        // Gestion avancée
        Route::post('/{id}/restore', [UserController::class, 'restore']);
        Route::delete('/{id}/force-delete', [UserController::class, 'forceDelete']);
    });

    // Statistiques & opérations en masse
    Route::get('/users-statistics', [UserController::class, 'getStatistics']);
    Route::get('/users-trashed', [UserController::class, 'getTrashed']);
    Route::post('/users-bulk-action', [UserController::class, 'bulkAction']);
    Route::get('/users-export', [UserController::class, 'export']);
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
    Route::get('/wishlists', [WishlistController::class, 'index']);
    Route::delete('/wishlists/{listing_id}', [WishlistController::class, 'destroy']);

    Route::get('/listings/country/{country_id}', [ListingController::class, 'getByCountry']);
    Route::get('/listings/by-city/{city_id}', [ListingController::class, 'getByCity']);

    Route::get('/listings/filter', [ListingController::class, 'filter']);
    Route::get('/listings/recent/city/{city_id}', [ListingController::class, 'getLastByCity']);

    Route::get('/listings', [ListingController::class, 'getAll']);

    Route::get('/listings/draft', [ListingController::class, 'getDraftListings']);
    Route::get('/listings/draft/{id}', [ListingController::class, 'getDraftListingById']);
    Route::delete('/listings/draft/{id}', [ListingController::class, 'deleteDraftListingById']);




    // Existing admin routes
    Route::apiResource('BankCards', CardController::class);

    // New user-specific routes
    Route::get('my-cards', [CardController::class, 'myCards']);
    Route::post('my-cards', [CardController::class, 'addMyCard']);
    Route::put('my-cards/{id}', [CardController::class, 'editMyCard']);
    Route::delete('my-cards/{id}', [CardController::class, 'deleteMyCard']);

    Route::patch('my-cards/{id}/set-default', [CardController::class, 'setAsDefault']);

    Route::post('/listings', [ListingController::class, 'store']);
    Route::put('/listings/complete/{id}', [ListingController::class, 'completeListing']);

    Route::get('/my-listing', [ListingController::class, 'my_listing']);

    Route::get('pricing', [ListingController::class, 'getPriceByModelId']);


    Route::apiResource('currency-rates', CurrencyExchangeRateController::class);

    Route::post('promo/check', [PromoCodeController::class, 'checkPromo']);

    Route::post('/upload-image', [ImageUploadController::class, 'upload']);

    // Obtenir tous les SOOMs d'un listing (Public)
    Route::get('/listings/{listingId}/sooms', [SoomController::class, 'getListingSooms']);

    // Obtenir le montant minimum requis pour un SOOM (Public)
    Route::get('/listings/{listingId}/minimum-soom', [SoomController::class, 'getMinimumSoomAmount']);

    // Obtenir le dernier SOOM d'un listing (Public)
    Route::get('/listings/{listingId}/last-soom', [SoomController::class, 'getLastSoom']);

    Route::get('/sooms/max', [SoomController::class, 'getMaxSoom']);
    // Route pour le max SOOM de l'utilisateur connecté
    Route::get('/sooms/max/me', [SoomController::class, 'getMyMaxSoom']);

    // Route pour tous les utilisateurs avec overbidding
    Route::get('/sooms/overbidding/users', [SoomController::class, 'getUsersWithOverbidding']);

    // === CRÉATION ET GESTION DES SOOMs ===

    // Créer un SOOM sur un listing (Acheteur)
    Route::post('/listings/{listingId}/soom', [SoomController::class, 'createSoom']);

    // === ACTIONS DU VENDEUR ===

    // Accepter un SOOM (Vendeur uniquement)
    Route::patch('/submissions/{submissionId}/accept', [SoomController::class, 'acceptSoom']);

    // Rejeter un SOOM (Vendeur uniquement)
    Route::patch('/submissions/{submissionId}/reject', [SoomController::class, 'rejectSoom']);

    // NOUVELLE: Valider la vente après acceptation (Vendeur uniquement)
    Route::post('/submissions/{submissionId}/validate-sale', [SoomController::class, 'validateSale']);

    // === ACTIONS DE L'ACHETEUR ===

    // Modifier un SOOM (Acheteur uniquement)
    Route::put('/submissions/{submissionId}/edit', [SoomController::class, 'editSoom']);

    // Annuler un SOOM (Acheteur uniquement)
    Route::delete('/submissions/{submissionId}/cancel', [SoomController::class, 'cancelSoom']);

    // === LISTES ET HISTORIQUES ===

    // Obtenir tous les SOOMs reçus sur mes listings (Vendeur)
    Route::get('/my-listings-sooms', [SoomController::class, 'getMyListingsSooms']);

    // Obtenir tous mes SOOMs envoyés (Acheteur)
    Route::get('/my-sooms', [SoomController::class, 'getMySooms']);

    // NOUVELLE: Obtenir toutes les ventes validées
    Route::get('/validated-sales', [SoomController::class, 'getValidatedSales']);

    // NOUVELLE: Obtenir les SOOMs en attente de validation (Vendeur)
    Route::get('/pending-validations', [SoomController::class, 'getPendingValidations']);

    // NOUVELLE: Obtenir les statistiques des SOOMs
    Route::get('/soom-stats', [SoomController::class, 'getSoomStats']);


    // Marquer un listing comme vendu
    Route::patch('/listings/{listingId}/mark-as-sold', [SoomController::class, 'markListingAsSold']);

    // Fermer un listing sans vente
    Route::patch('/listings/{listingId}/close', [SoomController::class, 'closeListing']);

    // Rouvrir un listing fermé
    Route::patch('/listings/{listingId}/reopen', [SoomController::class, 'reopenListing']);
    // My Garage CRUD operations
    Route::get('/my-garage', [MyGarageController::class, 'index']);           // GET all garage items
    Route::post('/my-garage', [MyGarageController::class, 'store']);          // POST create new item
    Route::get('/my-garage/default', [MyGarageController::class, 'getDefault']);
    Route::get('/my-garage/{id}', [MyGarageController::class, 'show']);       // GET single item
    Route::put('/my-garage/{id}', [MyGarageController::class, 'update']);     // PUT update item
    Route::patch('/my-garage/{id}', [MyGarageController::class, 'update']);   // PATCH update item (alternative)
    Route::delete('/my-garage/{id}', [MyGarageController::class, 'destroy']); // DELETE item
    Route::post('/my-garage/{id}/set-default', [MyGarageController::class, 'setDefault']);


    Route::get('motorcycle-data', [MyGarageController::class, 'getMotorcycleData']); // Fetch motorcycle data for dropdowns

    Route::patch('/users/{id}/activate', [UserController::class, 'activateUser']);
    Route::patch('/users/{id}/deactivate', [UserController::class, 'deactivateUser']);

    Route::get('/debug-wishlist/{id}', [ListingController::class, 'getDebugInfo']);


    Route::get('/listings/{id}', [ListingController::class, 'getById']);


    Route::get('/payments/history/user', [PaymentHistoryController::class, 'historyPaymentByUser']);
    Route::get('/payments/history/global', [PaymentHistoryController::class, 'historyPaymentGlobal']);
    Route::get('/payments/{id}', [PaymentHistoryController::class, 'show']);
    Route::get('/payments/stats/user', [PaymentHistoryController::class, 'userStats']);
});

Route::get('/listings/by-category/{category_id}', [ListingController::class, 'getByCategory']);

Route::apiResource('roles', App\Http\Controllers\RoleController::class);
Route::apiResource('motorcycle-types', MotorcycleTypeController::class);
Route::apiResource('motorcycle-brands', MotorcycleBrandController::class);
Route::apiResource('motorcycle-models', MotorcycleModelController::class);
Route::apiResource('motorcycle-years', MotorcycleYearController::class);
Route::apiResource('motorcycles', MotorcycleController::class);


Route::post('/motorcycles/import', [MotorcycleController::class, 'importMotorcycles']);

// Motorcycle Filter Routes - Optimized
Route::prefix('motorcycle')->group(function () {
    // Étape 1: Charger toutes les marques (rapide, avec cache)
    Route::get('/brands', [MotorcycleFilterController::class, 'getBrands']);

    // Dans api.php, ajouter dans le groupe motorcycle :
    Route::get('/brands/all', [MotorcycleFilterController::class, 'getAllBrands']); // Pour admin

    // Étape 2: Charger les modèles d'une marque (appelé quand l'utilisateur sélectionne une marque)
    Route::get('/models/{brandId}', [MotorcycleFilterController::class, 'getModelsByBrand'])
        ->where('brandId', '[0-9]+');

    // Étape 3: Charger les années d'un modèle (appelé quand l'utilisateur sélectionne un modèle)
    Route::get('/years/{modelId}', [MotorcycleFilterController::class, 'getYearsByModel'])
        ->where('modelId', '[0-9]+');

    // Bonus: Obtenir tous les détails d'une année spécifique
    Route::get('/details/{yearId}', [MotorcycleFilterController::class, 'getDetailsByYear'])
        ->where('yearId', '[0-9]+');

    // Utilitaire pour vider le cache en cas de mise à jour des données
    Route::post('/clear-cache', [MotorcycleFilterController::class, 'clearCache']);
});

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

// Get models with listings for a specific brand
Route::get('/brands/{brandId}/models-with-listings', [ListingController::class, 'getModelsWithListingsByBrand']);

Route::get('/types', action: [ListingController::class, 'getAllTypes']);


// Get years with listings for a specific brand and model
Route::get('/brands/{brandId}/models/{modelId}/years-with-listings', [ListingController::class, 'getYearsWithListingsByBrandAndModel']);

Route::get('/categorie/listings-count', [ListingController::class, 'getTypesWithListingCount']);

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
Route::get('/license-plates', [LicensePlateController::class, 'index']);
Route::get('/license-plates/{id}', [LicensePlateController::class, 'show']);





// Récupérer les formats de plaques par ville
Route::get('/cities/{cityId}/plate-formats', [LicensePlateController::class, 'getFormatsByCity']);

// Alternative avec plus de détails sur la ville
Route::get('/cities/{cityId}/plate-formats/details', [LicensePlateController::class, 'getFormatsByCityWithDetails']);

// Récupérer tous les formats par pays (groupés par ville)
Route::get('/countries/{countryId}/plate-formats', [LicensePlateController::class, 'getFormatsByCountry']);

// Route existante (pour référence)
Route::get('/license-plates/{id}/formatted', [LicensePlateController::class, 'showFormatted']);


Route::get('filter-options-license-plates', [FilterController::class, 'getLicensePlateFilterOptions']);
Route::get('filter-license-plates', [FilterController::class, 'filterLicensePlates']);


// Garder uniquement ces routes
Route::post('/firebase-login', [FirebaseAuthController::class, 'loginWithFirebase']); // Pour Google
// Authentification avec numéro et mot de passe
// Route::post('/login-phone-password', [FirebasePhoneAuthController::class, 'loginWithPhonePassword']);

// // Vérification OTP classique
// // Route::post('/verify-otp', [FirebasePhoneAuthController::class, 'verifyOTP']);

// // Envoi OTP Firebase (si besoin)
// Route::post('/send-firebase-otp', [FirebasePhoneAuthController::class, 'sendFirebaseOTP']);

// // Finaliser l'authentification Firebase
// Route::post('/complete-firebase-auth', [FirebasePhoneAuthController::class, 'completeFirebaseAuth']);


Route::post('/send-otp', [WhatsAppOtpController::class, 'sendOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/resend-otp-email', [AuthController::class, 'resendOtpEmail']); // Email only




Route::get('/recent', [ListingController::class, 'getRecentListings']);
// Password reset routes (no authentication required)
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);


// Change password route (authentication required)
Route::middleware('auth:api')->put('/change-password', [AuthController::class, 'changePassword']);

// Routes publiques PayTabs (pas d'authentification requise)
Route::prefix('paytabs')->name('paytabs.')->group(function () {
    // Callback automatique de PayTabs (appelé par leurs serveurs)
    Route::post('/callback', [PayTabsController::class, 'callback'])->name('callback');

    // Page de retour après paiement - ACCEPTE GET ET POST
    Route::match(['GET', 'POST'], '/return', [PayTabsController::class, 'return'])->name('return');

    // Webhook pour notifications push (optionnel)
    Route::post('/webhook', [PayTabsController::class, 'webhook'])->name('webhook');

    // Routes de résultat - Support GET et POST pour plus de flexibilité
    Route::match(['GET', 'POST'], '/success', [PayTabsController::class, 'paymentSuccess'])->name('success');
    Route::match(['GET', 'POST'], '/error', [PayTabsController::class, 'paymentError'])->name('error');
    Route::match(['GET', 'POST'], '/pending', [PayTabsController::class, 'paymentPending'])->name('pending');
    Route::match(['GET', 'POST'], '/cancel', [PayTabsController::class, 'paymentCancel'])->name('cancel');
});

// Routes additionnelles pour accès direct style dabapp.co
Route::prefix('payment')->name('payment.')->group(function () {
    Route::match(['GET', 'POST'], '/success', [PayTabsController::class, 'paymentSuccess'])->name('success');
    Route::match(['GET', 'POST'], '/error', [PayTabsController::class, 'paymentError'])->name('error');
    Route::match(['GET', 'POST'], '/pending', [PayTabsController::class, 'paymentPending'])->name('pending');
});

Route::middleware(['auth:api', 'admin'])->prefix('admin')->group(function () {
    Route::post('/listings/republish-paid', [ListingController::class, 'checkAndRepublishPaidListings']);
    Route::get('/listings/payment-stats', [ListingController::class, 'getListingPaymentStats']);
    Route::post('/payments/{paymentId}/force-verify', [ListingController::class, 'forcePaymentVerification']);
});

// User routes
Route::middleware(['auth:api'])->group(function () {
    Route::get('/listings/{listingId}/payment-status', [ListingController::class, 'checkListingPaymentStatus']);
});
Route::post('/paytabs/test-callback', [PayTabsController::class, 'testCallback']);

// Protected routes that require authentication
Route::middleware(['auth:api'])->group(function () {

    // PayTabs authenticated endpoints
    Route::prefix('paytabs')->name('paytabs.')->group(function () {
        // Créer un paiement (méthode générique)
        Route::post('create', [PayTabsController::class, 'createPayment'])->name('create');
    });

    // Paiements pour listings spécifiques
    Route::prefix('listings')->name('api.listings.')->group(function () {
        // Initier un paiement pour un listing
        Route::post('{listing}/payment', [PayTabsController::class, 'initiatePayment'])
            ->name('payment');
    });

    // Gestion des paiements
    Route::prefix('payments')->name('api.payments.')->group(function () {
        // Vérifier le statut d'un paiement
        Route::get('{payment}/status', [PayTabsController::class, 'checkPaymentStatus'])
            ->name('status');

        // Obtenir l'historique des paiements d'un utilisateur
        Route::get('history', [PayTabsController::class, 'getPaymentHistory'])
            ->name('history');

        // Obtenir les détails d'un paiement
        Route::get('{payment}', [PayTabsController::class, 'getPaymentDetails'])
            ->name('details');
    });
});


Route::get('/get-country', [AuthController::class, 'getCountry'])->name('get.country');

// Route::post('/test-email', [AuthController::class, 'testEmail']);


Route::get('/test-email', [SoomController::class, 'testEmail']);

// Route::get('/test-email-simple', function () {
//     try {
//         \Log::info('Testing simple email...');

//         Mail::raw('This is a test email from Laravel', function ($message) {
//             $message->to('test@example.com')
//                 ->subject('Laravel Test Email');
//         });

//         return 'Email test sent - check logs';
//     } catch (\Exception $e) {
//         \Log::error('Email test failed: ' . $e->getMessage());
//         return 'Email test failed: ' . $e->getMessage();
//     }
// });
/*
|--------------------------------------------------------------------------
| Guide API Routes - JWT Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('guides')->group(function () {

    // ========================================
    // GUIDE CATEGORIES ROUTES
    // ========================================
    Route::prefix('categories')->group(function () {
        // Public routes
        Route::get('/', [GuideCategoryController::class, 'index']);
        Route::get('/{id}', [GuideCategoryController::class, 'show']);
        Route::get('/{id}/guides', [GuideCategoryController::class, 'getGuidesByCategory']);

        // Authenticated routes (JWT)
        Route::middleware(['auth:api'])->group(function () {
            Route::post('/', [GuideCategoryController::class, 'store']);
            Route::put('/{id}', [GuideCategoryController::class, 'update']);
            Route::delete('/{id}', [GuideCategoryController::class, 'destroy']);
        });
    });

    // ========================================
    // GUIDE TAGS ROUTES
    // ========================================
    Route::prefix('tags')->group(function () {
        // Public routes
        Route::get('/', [GuideTagController::class, 'index']);
        Route::get('/popular', [GuideTagController::class, 'popular']);
        Route::get('/{slug}', [GuideTagController::class, 'show']);
        Route::get('/{slug}/guides', [GuideTagController::class, 'getGuidesByTag']);

        // Authenticated routes (JWT)
        Route::middleware(['auth:api'])->group(function () {
            Route::post('/', [GuideTagController::class, 'store']);
            Route::put('/{id}', [GuideTagController::class, 'update']);
            Route::delete('/{id}', [GuideTagController::class, 'destroy']);
        });
    });

    // ========================================
    // GUIDES ROUTES
    // ========================================

    // Routes spéciales AVANT le slug (important pour éviter les conflits)
    Route::get('/featured', [GuideController::class, 'featured']);
    Route::get('/popular', [GuideController::class, 'popular']);
    Route::get('/latest', [GuideController::class, 'latest']);

    // Public routes
    Route::get('/', [GuideController::class, 'index']);
    Route::get('/{slug}', [GuideController::class, 'show']);

    // Commentaires publics (lecture seule)
    Route::get('/{id}/comments', [GuideCommentController::class, 'index']);

    // Authenticated routes (JWT)
    Route::middleware('auth:api')->group(function () {
        // Gestion des guides
        Route::get('/my/guides', [GuideController::class, 'myGuides']);
        Route::post('/', [GuideController::class, 'store']);
        Route::put('/{id}', [GuideController::class, 'update']);
        Route::delete('/{id}', [GuideController::class, 'destroy']);
        Route::post('/{id}/publish', [GuideController::class, 'publish']);
        Route::post('/{id}/archive', [GuideController::class, 'archive']);

        // ========================================
        // GUIDE IMAGES ROUTES
        // ========================================
        Route::prefix('{guide_id}/images')->group(function () {
            Route::get('/', [GuideImageController::class, 'index']);
            Route::post('/', [GuideImageController::class, 'store']);
            Route::put('/{id}', [GuideImageController::class, 'update']);
            Route::delete('/{id}', [GuideImageController::class, 'destroy']);
            Route::post('/reorder', [GuideImageController::class, 'reorder']);
        });

        // ========================================
        // GUIDE LIKES ROUTES
        // ========================================
        Route::post('/{id}/like', [GuideLikeController::class, 'like']);
        Route::delete('/{id}/unlike', [GuideLikeController::class, 'unlike']);
        Route::post('/{id}/toggle-like', [GuideLikeController::class, 'toggleLike']);
        Route::get('/my/liked', [GuideLikeController::class, 'myLikedGuides']);
        Route::get('/{id}/likes', [GuideLikeController::class, 'getGuideLikes']);

        // ========================================
        // GUIDE BOOKMARKS ROUTES
        // ========================================
        Route::post('/{id}/bookmark', [GuideBookmarkController::class, 'bookmark']);
        Route::delete('/{id}/unbookmark', [GuideBookmarkController::class, 'unbookmark']);
        Route::post('/{id}/toggle-bookmark', [GuideBookmarkController::class, 'toggleBookmark']);
        Route::get('/my/bookmarks', [GuideBookmarkController::class, 'myBookmarks']);
        Route::get('/my/bookmarks/count', [GuideBookmarkController::class, 'countMyBookmarks']);
        Route::delete('/my/bookmarks/clear', [GuideBookmarkController::class, 'clearAllBookmarks']);
        Route::get('/{id}/bookmark-status', [GuideBookmarkController::class, 'checkBookmarkStatus']);
        Route::post('/bookmarks/batch', [GuideBookmarkController::class, 'batchCheckBookmarks']);

        // ========================================
        // GUIDE COMMENTS ROUTES
        // ========================================
        Route::post('/{id}/comments', [GuideCommentController::class, 'store']);
        Route::put('/comments/{commentId}', [GuideCommentController::class, 'update']);
        Route::delete('/comments/{commentId}', [GuideCommentController::class, 'destroy']);
        Route::get('/my/comments', [GuideCommentController::class, 'myComments']);

        // Modération des commentaires (Admin uniquement)
        Route::post('/comments/{commentId}/approve', [GuideCommentController::class, 'approve']);
        Route::post('/comments/{commentId}/reject', [GuideCommentController::class, 'reject']);
        Route::get('/comments/pending', [GuideCommentController::class, 'pending']);
    });
});

Route::prefix('comparaison/motorcycles')->group(function () {
    Route::get('/types', [MotorcycleComparisonController::class, 'getTypes']);
    Route::get('/brands', [MotorcycleComparisonController::class, 'getBrands']);
    Route::get('/models', [MotorcycleComparisonController::class, 'getModels']);
    Route::get('/years', [MotorcycleComparisonController::class, 'getYears']);
    Route::get('/details/{yearId}', [MotorcycleComparisonController::class, 'getMotorcycleDetails']);
    Route::post('/compare', [MotorcycleComparisonController::class, 'compare']);
});



/*
|--------------------------------------------------------------------------
| API Routes - Events Module
|--------------------------------------------------------------------------
*/


// Event Categories (Public & Protected)
Route::prefix('event-categories')->group(function () {
    // Public routes
    Route::get('/', [EventCategoryController::class, 'index']);
    Route::get('/{id}', [EventCategoryController::class, 'show']);
    Route::get('/{id}/events', [EventCategoryController::class, 'events']);

    // Protected routes - JWT Authentication
    Route::middleware('auth:api')->group(function () {
        Route::post('/', [EventCategoryController::class, 'store']);
        Route::put('/{id}', [EventCategoryController::class, 'update']);
        Route::delete('/{id}', [EventCategoryController::class, 'destroy']);
    });
});

// Events (Public & Protected)
Route::prefix('events')->group(function () {
    // Public routes
    Route::get('/', [EventController::class, 'index']);
    Route::get('/upcoming', [EventController::class, 'upcoming']);
    Route::get('/featured', [EventController::class, 'featured']);
    Route::get('/{id}', [EventController::class, 'show']);
    Route::get('/{eventId}/participants', [EventParticipantController::class, 'index']);
    Route::get('/{eventId}/reviews', [EventReviewController::class, 'index']);
    Route::get('/{eventId}/reviews/{reviewId}', [EventReviewController::class, 'show']);
    Route::get('/{eventId}/activities', [EventActivityController::class, 'index']);
    Route::get('/{eventId}/activities/{activityId}', [EventActivityController::class, 'show']);
    Route::get('/{eventId}/tickets', [EventTicketController::class, 'index']);
    Route::get('/{eventId}/tickets/{ticketId}', [EventTicketController::class, 'show']);
    Route::get('/{eventId}/contacts', [EventContactController::class, 'index']);
    Route::get('/{eventId}/contacts/{contactId}', [EventContactController::class, 'show']);
    Route::get('/{eventId}/faqs', [EventFaqController::class, 'index']);
    Route::get('/{eventId}/faqs/{faqId}', [EventFaqController::class, 'show']);
    Route::get('/{eventId}/updates', [EventUpdateController::class, 'index']);
    Route::get('/{eventId}/updates/{updateId}', [EventUpdateController::class, 'show']);
    Route::get('/{eventId}/updates/important', [EventUpdateController::class, 'important']);
    Route::get('/{eventId}/updates/latest', [EventUpdateController::class, 'latest']);

    // Protected routes - JWT Authentication
    Route::middleware('auth:api')->group(function () {
        // Event CRUD
        Route::post('/', [EventController::class, 'store']);
        Route::put('/{id}', [EventController::class, 'update']);
        Route::delete('/{id}', [EventController::class, 'destroy']);
        Route::get('/{id}/statistics', [EventController::class, 'statistics']);
        Route::post('/{id}/publish', [EventController::class, 'togglePublish']);

        // Event registration & participants
        Route::post('/{eventId}/register', [EventParticipantController::class, 'register']);
        Route::delete('/{eventId}/unregister', [EventParticipantController::class, 'unregister']);
        Route::put('/{eventId}/participants/{participantId}/confirm', [EventParticipantController::class, 'confirm']);
        Route::put('/{eventId}/participants/{participantId}/check-in', [EventParticipantController::class, 'checkIn']);
        Route::get('/{eventId}/participants/statistics', [EventParticipantController::class, 'statistics']);
        Route::get('/{eventId}/participants/{participantId}', [EventParticipantController::class, 'show']);
        Route::get('/{eventId}/my-registration', [EventParticipantController::class, 'myRegistration']);

        // Event reviews
        Route::post('/{eventId}/reviews', [EventReviewController::class, 'store']);
        Route::put('/{eventId}/reviews/{reviewId}', [EventReviewController::class, 'update']);
        Route::delete('/{eventId}/reviews/{reviewId}', [EventReviewController::class, 'destroy']);
        Route::get('/{eventId}/my-review', [EventReviewController::class, 'myReview']);
        Route::get('/{eventId}/reviews/can-review', [EventReviewController::class, 'canReview']);

        // Event favorites
        Route::post('/{eventId}/favorite', [EventFavoriteController::class, 'store']);
        Route::delete('/{eventId}/unfavorite', [EventFavoriteController::class, 'destroy']);
        Route::get('/{eventId}/is-favorite', [EventFavoriteController::class, 'isFavorite']);
        Route::post('/{eventId}/toggle-favorite', [EventFavoriteController::class, 'toggle']);

        // Event activities (Organizer only)
        Route::post('/{eventId}/activities', [EventActivityController::class, 'store']);
        Route::put('/{eventId}/activities/{activityId}', [EventActivityController::class, 'update']);
        Route::delete('/{eventId}/activities/{activityId}', [EventActivityController::class, 'destroy']);

        // Event tickets
        Route::post('/{eventId}/tickets', [EventTicketController::class, 'store']);
        Route::put('/{eventId}/tickets/{ticketId}', [EventTicketController::class, 'update']);
        Route::delete('/{eventId}/tickets/{ticketId}', [EventTicketController::class, 'destroy']);
        Route::post('/{eventId}/tickets/{ticketId}/purchase', [EventTicketController::class, 'purchase']);
        Route::get('/{eventId}/tickets/statistics', [EventTicketController::class, 'statistics']);
        Route::get('/{eventId}/tickets/purchases', [EventTicketController::class, 'eventPurchases']);
        Route::post('/{eventId}/tickets/{ticketId}/toggle-active', [EventTicketController::class, 'toggleActive']);

        // Event contacts (Organizer only)
        Route::post('/{eventId}/contacts', [EventContactController::class, 'store']);
        Route::put('/{eventId}/contacts/{contactId}', [EventContactController::class, 'update']);
        Route::delete('/{eventId}/contacts/{contactId}', [EventContactController::class, 'destroy']);

        // Event FAQs (Organizer only)
        Route::post('/{eventId}/faqs', [EventFaqController::class, 'store']);
        Route::put('/{eventId}/faqs/{faqId}', [EventFaqController::class, 'update']);
        Route::delete('/{eventId}/faqs/{faqId}', [EventFaqController::class, 'destroy']);
        Route::post('/{eventId}/faqs/reorder', [EventFaqController::class, 'reorder']);
        Route::delete('/{eventId}/faqs/bulk-delete', [EventFaqController::class, 'bulkDelete']);
        Route::get('/{eventId}/faqs/search', [EventFaqController::class, 'search']);

        // Event updates (Organizer only)
        Route::post('/{eventId}/updates', [EventUpdateController::class, 'store']);
        Route::put('/{eventId}/updates/{updateId}', [EventUpdateController::class, 'update']);
        Route::delete('/{eventId}/updates/{updateId}', [EventUpdateController::class, 'destroy']);
        Route::delete('/{eventId}/updates/bulk-delete', [EventUpdateController::class, 'bulkDelete']);
    });
});

// Tickets routes (Protected)
Route::middleware('auth:api')->prefix('tickets')->group(function () {
    Route::get('/{purchaseId}', [EventTicketController::class, 'showPurchase']);
    Route::post('/{purchaseId}/check-in', [EventTicketController::class, 'checkIn']);
    Route::get('/verify/{qrCode}', [EventTicketController::class, 'verifyQRCode']);
});

// User-specific event routes (Protected)
Route::middleware('auth:api')->group(function () {
    Route::get('/my-events', [EventParticipantController::class, 'myEvents']);
    Route::get('/my-favorite-events', [EventFavoriteController::class, 'myFavorites']);
    Route::delete('/my-favorite-events/clear', [EventFavoriteController::class, 'clearAll']);
    Route::get('/my-favorite-events/count', [EventFavoriteController::class, 'count']);
    Route::get('/my-tickets', [EventTicketController::class, 'myTickets']);
    Route::get('/my-organized-events', [EventController::class, 'myOrganizedEvents']);
    Route::get('/my-reviews', [EventReviewController::class, 'myReviews']);
    Route::get('/my-event-updates', [EventUpdateController::class, 'myEventUpdates']);
});

// Event Sponsors (Protected)
Route::prefix('event-sponsors')->group(function () {
    // Protected routes - JWT Authentication
    Route::middleware('auth:api')->group(function () {
        Route::get('/', [EventSponsorController::class, 'index']);
        Route::post('/', [EventSponsorController::class, 'store']);
        Route::get('/{id}', [EventSponsorController::class, 'show']);
        Route::put('/{id}', [EventSponsorController::class, 'update']);
        Route::delete('/{id}', [EventSponsorController::class, 'destroy']);
    });
});

// Event Sponsors - Event Management (Protected)
Route::middleware('auth:api')->prefix('events')->group(function () {
    Route::get('/{eventId}/sponsors', [EventSponsorController::class, 'eventSponsors']);
    Route::post('/{eventId}/sponsors/{sponsorId}/attach', [EventSponsorController::class, 'attachToEvent']);
    Route::delete('/{eventId}/sponsors/{sponsorId}/detach', [EventSponsorController::class, 'detachFromEvent']);
    Route::put('/{eventId}/sponsors/{sponsorId}/update-level', [EventSponsorController::class, 'updateSponsorLevel']);
});


// Public newsletter routes
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
Route::post('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe']);

// Authenticated newsletter routes
Route::middleware('auth:api')->group(function () {
    Route::get('/newsletter/preferences', [NewsletterController::class, 'getPreferences']);
    Route::put('/newsletter/preferences', [NewsletterController::class, 'updatePreferences']);
});

// Newsletter campaign routes (Admin only)
Route::middleware(['auth:api'])->prefix('newsletter')->group(function () {
    Route::get('/campaigns', [NewsletterCampaignController::class, 'index']);
    Route::post('/campaigns', [NewsletterCampaignController::class, 'store']);
    Route::get('/campaigns/{id}', [NewsletterCampaignController::class, 'show']);
    Route::put('/campaigns/{id}', [NewsletterCampaignController::class, 'update']);
    Route::delete('/campaigns/{id}', [NewsletterCampaignController::class, 'destroy']);
    Route::post('/campaigns/{id}/send', [NewsletterCampaignController::class, 'send']);
    Route::get('/campaigns/{id}/stats', [NewsletterCampaignController::class, 'stats']);
    Route::post('/campaigns/{id}/duplicate', [NewsletterCampaignController::class, 'duplicate']);
});

// ============================================
// POI TYPE ROUTES
// ============================================

Route::get('/poi-types', [PoiTypeController::class, 'index']);
Route::get('/poi-types/{id}', [PoiTypeController::class, 'show']);

Route::middleware(['auth:api'])->group(function () {
    Route::post('/poi-types', [PoiTypeController::class, 'store']);
    Route::put('/poi-types/{id}', [PoiTypeController::class, 'update']);
    Route::delete('/poi-types/{id}', [PoiTypeController::class, 'destroy']);
});

// ============================================
// POI SERVICE ROUTES
// ============================================

Route::get('/poi-services', [PoiServiceController::class, 'index']);
Route::get('/poi-services/{id}', [PoiServiceController::class, 'show']);

Route::middleware(['auth:api'])->group(function () {
    Route::post('/poi-services', [PoiServiceController::class, 'store']);
    Route::put('/poi-services/{id}', [PoiServiceController::class, 'update']);
    Route::delete('/poi-services/{id}', [PoiServiceController::class, 'destroy']);
});

// ============================================
// POINTS OF INTEREST ROUTES
// ============================================

Route::middleware(['auth:api'])->group(function () {
    Route::get('/pois', [PointOfInterestController::class, 'index']);
    Route::post('/pois', [PointOfInterestController::class, 'store']);
    Route::get('/pois/{id}', [PointOfInterestController::class, 'show']);
    Route::put('/pois/{id}', [PointOfInterestController::class, 'update']);
    Route::delete('/pois/{id}', [PointOfInterestController::class, 'destroy']);
    Route::post('/pois/{id}/favorite', [PointOfInterestController::class, 'toggleFavorite']);
    Route::get('/pois/nearby', [PointOfInterestController::class, 'nearby']);
});

// ============================================
// POI REVIEW ROUTES
// ============================================

Route::middleware(['auth:api'])->group(function () {
    Route::get('/pois/{poi_id}/reviews', [PoiReviewController::class, 'index']);
    Route::post('/pois/{poi_id}/reviews', [PoiReviewController::class, 'store']);
    Route::put('/pois/{poi_id}/reviews/{id}', [PoiReviewController::class, 'update']);
    Route::delete('/pois/{poi_id}/reviews/{id}', [PoiReviewController::class, 'destroy']);
});

// ============================================
// POI REPORT ROUTES
// ============================================

Route::middleware(['auth:api'])->group(function () {
    Route::get('/pois/{poi_id}/reports', [PoiReportController::class, 'index']);
    Route::post('/pois/{poi_id}/reports', [PoiReportController::class, 'store']);
    Route::get('/pois/{poi_id}/reports/{id}', [PoiReportController::class, 'show']);
    Route::put('/pois/{poi_id}/reports/{id}/status', [PoiReportController::class, 'updateStatus']);
    Route::delete('/pois/{poi_id}/reports/{id}', [PoiReportController::class, 'destroy']);

    // Admin routes for reports
    Route::get('/reports/pending', [PoiReportController::class, 'pending']);
    Route::get('/reports/stats', [PoiReportController::class, 'stats']);

    // User's own reports
    Route::get('/user/reports', [PoiReportController::class, 'userReports']);
});

// ============================================
// ROUTE CATEGORY ROUTES
// ============================================

Route::get('/route-categories', [RouteCategoryController::class, 'index']);
Route::get('/route-categories/{id}', [RouteCategoryController::class, 'show']);

Route::middleware(['auth:api'])->group(function () {
    Route::post('/route-categories', [RouteCategoryController::class, 'store']);
    Route::put('/route-categories/{id}', [RouteCategoryController::class, 'update']);
    Route::delete('/route-categories/{id}', [RouteCategoryController::class, 'destroy']);
});

// ============================================
// ROUTE TAG ROUTES
// ============================================

Route::get('/route-tags', [RouteTagController::class, 'index']);
Route::get('/route-tags/{id}', [RouteTagController::class, 'show']);
Route::get('/route-tags/slug/{slug}', [RouteTagController::class, 'showBySlug']);
Route::post('/route-tags/search', [RouteTagController::class, 'search']);

Route::middleware(['auth:api'])->group(function () {
    Route::post('/route-tags', [RouteTagController::class, 'store']);
    Route::put('/route-tags/{id}', [RouteTagController::class, 'update']);
    Route::delete('/route-tags/{id}', [RouteTagController::class, 'destroy']);
});

// ============================================
// ROUTE ROUTES
// ============================================

Route::middleware(['auth:api'])->group(function () {
    Route::get('/routes', [RouteController::class, 'index']);
    Route::post('/routes', [RouteController::class, 'store']);
    Route::get('/routes/{id}', [RouteController::class, 'show']);
    Route::put('/routes/{id}', [RouteController::class, 'update']);
    Route::delete('/routes/{id}', [RouteController::class, 'destroy']);
    Route::post('/routes/{id}/like', [RouteController::class, 'toggleLike']);
    Route::post('/routes/{id}/favorite', [RouteController::class, 'toggleFavorite']);
});

// ============================================
// ROUTE WAYPOINT ROUTES
// ============================================

Route::middleware(['auth:api'])->group(function () {
    Route::get('/routes/{route_id}/waypoints', [RouteWaypointController::class, 'index']);
    Route::post('/routes/{route_id}/waypoints', [RouteWaypointController::class, 'store']);
    Route::get('/routes/{route_id}/waypoints/{id}', [RouteWaypointController::class, 'show']);
    Route::put('/routes/{route_id}/waypoints/{id}', [RouteWaypointController::class, 'update']);
    Route::delete('/routes/{route_id}/waypoints/{id}', [RouteWaypointController::class, 'destroy']);
    Route::put('/routes/{route_id}/waypoints/{id}/reorder', [RouteWaypointController::class, 'reorder']);
});

// ============================================
// ROUTE REVIEW ROUTES
// ============================================

Route::middleware(['auth:api'])->group(function () {
    Route::get('/routes/{route_id}/reviews', [RouteReviewController::class, 'index']);
    Route::post('/routes/{route_id}/reviews', [RouteReviewController::class, 'store']);
    Route::delete('/routes/{route_id}/reviews/{id}', [RouteReviewController::class, 'destroy']);
});

// ============================================
// ROUTE WARNING ROUTES
// ============================================

Route::middleware(['auth:api'])->group(function () {
    Route::get('/routes/{route_id}/warnings', [RouteWarningController::class, 'index']);
    Route::post('/routes/{route_id}/warnings', [RouteWarningController::class, 'store']);
    Route::get('/routes/{route_id}/warnings/{id}', [RouteWarningController::class, 'show']);
    Route::put('/routes/{route_id}/warnings/{id}', [RouteWarningController::class, 'update']);
    Route::delete('/routes/{route_id}/warnings/{id}', [RouteWarningController::class, 'destroy']);
    Route::put('/routes/{route_id}/warnings/{id}/deactivate', [RouteWarningController::class, 'deactivate']);

    // All active warnings
    Route::get('/warnings/active', [RouteWarningController::class, 'getAllActive']);
});

// ============================================
// ROUTE COMPLETION ROUTES
// ============================================

Route::middleware(['auth:api'])->group(function () {
    Route::get('/routes/{route_id}/completions', [RouteCompletionController::class, 'index']);
    Route::post('/routes/{route_id}/completions', [RouteCompletionController::class, 'store']);
    Route::get('/routes/{route_id}/completions/{id}', [RouteCompletionController::class, 'show']);
    Route::put('/routes/{route_id}/completions/{id}', [RouteCompletionController::class, 'update']);
    Route::delete('/routes/{route_id}/completions/{id}', [RouteCompletionController::class, 'destroy']);
    Route::get('/routes/{route_id}/check-completion', [RouteCompletionController::class, 'checkCompletion']);

    // User completion stats
    Route::get('/user/completions', [RouteCompletionController::class, 'userCompletions']);
    Route::get('/user/completion-stats', [RouteCompletionController::class, 'userStats']);
});
