<?php

use Illuminate\Support\Facades\Route;

// ============================================
// CONTROLLERS IMPORTS
// ============================================
use App\Http\Controllers\{
    AuthController,
    BikePartBrandController,
    BikePartCategoryController,
    CardController,
    CardTypeController,
    CurrencyExchangeRateController,
    EventActivityController,
    EventCategoryController,
    EventContactController,
    EventController,
    EventFaqController,
    EventFavoriteController,
    EventParticipantController,
    EventReviewController,
    EventSponsorController,
    EventTicketController,
    EventUpdateController,
    FilterController,
    FirebaseAuthController,
    GuideBookmarkController,
    GuideCategoryController,
    GuideCommentController,
    GuideController,
    GuideImageController,
    GuideLikeController,
    GuideTagController,
    ImageUploadController,
    LicensePlateController,
    LicensePlateFilterController,
    ListingAuctionController,
    ListingController,
    ListingTypeController,
    LocationController,
    MotorcycleBrandController,
    MotorcycleComparisonController,
    MotorcycleController,
    MotorcycleFilterController,
    MotorcycleImportController,
    MotorcycleModelController,
    MotorcycleTypeController,
    MotorcycleYearController,
    MyGarageController,
    NewsletterCampaignController,
    NewsletterController,
    PaymentHistoryController,
    PayTabsController,
    PermissionController,
    PlateFormatController,
    PlateGeneratorController,
    PointOfInterestController,
    PoiReportController,
    PoiReviewController,
    PoiServiceController,
    PoiTypeController,
    PricingRulesLicencePlateController,
    PricingRulesMotorcycleController,
    PricingRulesSparepartController,
    PromoCodeController,
    RoleController,
    RolePermissionController,
    RouteCategoryController,
    RouteCompletionController,
    RouteController,
    RouteReviewController,
    RouteTagController,
    RouteWarningController,
    RouteWaypointController,
    SoomController,
    SvgGeneratorController,
    UserController,
    WhatsAppOtpController,
    WishlistController
};

// ============================================
// PUBLIC AUTHENTICATION ROUTES
// ============================================
Route::prefix('auth')->group(function () {
    // Registration & Login
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // OTP Management
    Route::post('/send-otp', [WhatsAppOtpController::class, 'sendOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/resend-otp-email', [AuthController::class, 'resendOtpEmail']);

    // Password Reset
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Firebase Authentication
    Route::post('/firebase-login', [FirebaseAuthController::class, 'loginWithFirebase']);

    // Country Information
    Route::get('/get-country', [AuthController::class, 'getCountry'])->name('get.country');
    Route::get('/countries', [AuthController::class, 'getAllCountries']);
});

// ============================================
// PUBLIC LOCATION ROUTES
// ============================================
Route::prefix('locations')->group(function () {
    Route::get('/', [LocationController::class, 'index']);

    // Countries
    Route::post('/countries', [LocationController::class, 'storeCountry']);
    Route::put('/countries/{id}', [LocationController::class, 'updateCountry']);
    Route::delete('/countries/{id}', [LocationController::class, 'destroyCountry']);

    // Cities
    Route::post('/cities', [LocationController::class, 'storeCity']);
    Route::put('/cities/{id}', [LocationController::class, 'updateCity']);
    Route::delete('/cities/{id}', [LocationController::class, 'destroyCity']);
});

// ============================================
// PUBLIC MOTORCYCLE DATA ROUTES
// ============================================
Route::prefix('motorcycles')->group(function () {
    // Basic CRUD
    Route::apiResource('/', MotorcycleController::class)->parameters(['' => 'motorcycle']);
    Route::apiResource('/types', MotorcycleTypeController::class)->parameters(['' => 'type']);
    Route::apiResource('/brands', MotorcycleBrandController::class)->parameters(['' => 'brand']);
    Route::apiResource('/models', MotorcycleModelController::class)->parameters(['' => 'model']);
    Route::apiResource('/years', MotorcycleYearController::class)->parameters(['' => 'year']);

    // Import
    Route::post('/import', [MotorcycleController::class, 'importMotorcycles']);
    Route::post('/import-alt', [MotorcycleImportController::class, 'import']);

    // Filtering & Search
    Route::get('/brands', [MotorcycleFilterController::class, 'getBrands']);
    Route::get('/brands/all', [MotorcycleFilterController::class, 'getAllBrands']);
    Route::get('/models/{brandId}', [MotorcycleFilterController::class, 'getModelsByBrand']);
    Route::get('/years/{modelId}', [MotorcycleFilterController::class, 'getYearsByModel']);
    Route::get('/details/{yearId}', [MotorcycleFilterController::class, 'getDetailsByYear']);
    Route::get('/brand/{brandId}', [MotorcycleFilterController::class, 'getByBrand']);
    Route::get('/year/{yearId}', [MotorcycleFilterController::class, 'getByYear']);
    Route::post('/clear-cache', [MotorcycleFilterController::class, 'clearCache']);
});

// ============================================
// PUBLIC MOTORCYCLE COMPARISON ROUTES
// ============================================
Route::prefix('comparison/motorcycles')->group(function () {
    Route::get('/types', [MotorcycleComparisonController::class, 'getTypes']);
    Route::get('/brands', [MotorcycleComparisonController::class, 'getBrands']);
    Route::get('/models', [MotorcycleComparisonController::class, 'getModels']);
    Route::get('/years', [MotorcycleComparisonController::class, 'getYears']);
    Route::get('/details/{yearId}', [MotorcycleComparisonController::class, 'getMotorcycleDetails']);
    Route::post('/compare', [MotorcycleComparisonController::class, 'compare']);
});

// ============================================
// PUBLIC LISTINGS ROUTES
// ============================================
Route::prefix('listings')->group(function () {
    Route::get('/', [ListingController::class, 'getAll']);
    Route::get('/recent', [ListingController::class, 'getRecentListings']);
    Route::get('/types', [ListingController::class, 'getAllTypes']);
    Route::get('/{id}', [ListingController::class, 'getById']);
    Route::get('/country/{country_id}', [ListingController::class, 'getByCountry']);
    Route::get('/by-city/{city_id}', [ListingController::class, 'getByCity']);
    Route::get('/by-category/{category_id}', [ListingController::class, 'getByCategory']);
    Route::get('/recent/city/{city_id}', [ListingController::class, 'getLastByCity']);
    Route::get('/search-by-model', [ListingController::class, 'searchByCategoryAndModel']);
    Route::get('/filter', [ListingController::class, 'filter']);
    Route::get('/debug-wishlist/{id}', [ListingController::class, 'getDebugInfo']);

    // Statistics
    Route::get('/brands/listings-count', [ListingController::class, 'getBrandsWithListingCount']);
    Route::get('/brands/{brandId}/models-with-listings', [ListingController::class, 'getModelsWithListingsByBrand']);
    Route::get('/brands/{brandId}/models/{modelId}/years-with-listings', [ListingController::class, 'getYearsWithListingsByBrandAndModel']);
    Route::get('/categorie/listings-count', [ListingController::class, 'getTypesWithListingCount']);
    Route::get('/categories/{categoryId}/price-range', [ListingController::class, 'getPriceRangeByCategory'])->where('categoryId', '[1-3]');
    Route::get('/bike-part-categories', [ListingController::class, 'getBikePartCategoriesWithListingCount']);
    Route::get('/bike-part-brands', [ListingController::class, 'getBikePartBrandsWithListingCount']);
});

// ============================================
// PUBLIC FILTERS ROUTES
// ============================================
Route::prefix('filter')->group(function () {
    Route::get('/motorcycles', [FilterController::class, 'filterMotorcycles']);
    Route::get('/spare-parts', [FilterController::class, 'filterSpareParts']);
    Route::get('/license-plates', [FilterController::class, 'filterLicensePlates']);
    Route::get('-options-license-plates', [FilterController::class, 'getLicensePlateFilterOptions']);
});

// ============================================
// PUBLIC BIKE PARTS ROUTES
// ============================================
Route::apiResource('bike-part-brands', BikePartBrandController::class);
Route::apiResource('bike-part-categories', BikePartCategoryController::class);

// ============================================
// PUBLIC LICENSE PLATES ROUTES
// ============================================
Route::prefix('license-plates')->group(function () {
    Route::get('/', [LicensePlateController::class, 'index']);
    Route::get('/{id}', [LicensePlateController::class, 'show']);
    Route::get('/{id}/formatted', [LicensePlateController::class, 'showFormatted']);
    Route::get('/cities/{cityId}/plate-formats', [LicensePlateController::class, 'getFormatsByCity']);
    Route::get('/cities/{cityId}/plate-formats/details', [LicensePlateController::class, 'getFormatsByCityWithDetails']);
    Route::get('/countries/{countryId}/plate-formats', [LicensePlateController::class, 'getFormatsByCountry']);
});

// ============================================
// PUBLIC PLATE GENERATOR ROUTES
// ============================================
Route::post('/generate-plate', [PlateGeneratorController::class, 'generatePlate']);
Route::get('/download-plate/{filename}', [PlateGeneratorController::class, 'downloadPlate']);

// ============================================
// PUBLIC GUIDES ROUTES
// ============================================
Route::prefix('guides')->group(function () {
    // Categories
    Route::prefix('categories')->group(function () {
        Route::get('/', [GuideCategoryController::class, 'index']);
        Route::get('/{id}', [GuideCategoryController::class, 'show']);
        Route::get('/{id}/guides', [GuideCategoryController::class, 'getGuidesByCategory']);
    });

    // Tags
    Route::prefix('tags')->group(function () {
        Route::get('/', [GuideTagController::class, 'index']);
        Route::get('/popular', [GuideTagController::class, 'popular']);
        Route::get('/{slug}', [GuideTagController::class, 'show']);
        Route::get('/{slug}/guides', [GuideTagController::class, 'getGuidesByTag']);
    });

    // Guides - Special routes first
    Route::get('/starter', [GuideController::class, 'starter']);
    Route::get('/featured', [GuideController::class, 'featured']);
    Route::get('/popular', [GuideController::class, 'popular']);
    Route::get('/latest', [GuideController::class, 'latest']);
    Route::get('/id/{id}', [GuideController::class, 'showById']);
    Route::get('/category/{category_id}', [GuideController::class, 'getByCategory']);
    Route::get('/tag/{tag_slug}', [GuideController::class, 'getByTag']);
    Route::get('/tag/id/{tag_id}', [GuideController::class, 'getByTagId']);
    Route::get('/', [GuideController::class, 'index']);
    Route::get('/{slug}', [GuideController::class, 'show']);
    Route::get('/{id}/comments', [GuideCommentController::class, 'index']);
});

// ============================================
// PUBLIC EVENTS ROUTES
// ============================================
Route::prefix('events')->group(function () {
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
});

Route::prefix('event-categories')->group(function () {
    Route::get('/', [EventCategoryController::class, 'index']);
    Route::get('/{id}', [EventCategoryController::class, 'show']);
    Route::get('/{id}/events', [EventCategoryController::class, 'events']);
});

// ============================================
// PUBLIC NEWSLETTER ROUTES
// ============================================
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
Route::post('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe']);

// ============================================
// PUBLIC POI ROUTES
// ============================================
Route::get('/poi-types', [PoiTypeController::class, 'index']);
Route::get('/poi-types/stats', [PoiTypeController::class, 'stats']);
Route::get('/poi-types/{id}', [PoiTypeController::class, 'show']);
Route::get('/poi-types/{id}/pois', [PoiTypeController::class, 'getPois']);
Route::get('/poi-services', [PoiServiceController::class, 'index']);
Route::get('/poi-services/{id}', [PoiServiceController::class, 'show']);

// ============================================
// PUBLIC ROUTE ROUTES
// ============================================
Route::get('/route-categories', [RouteCategoryController::class, 'index']);
Route::get('/route-categories/{id}', [RouteCategoryController::class, 'show']);
Route::get('/route-tags', [RouteTagController::class, 'index']);
Route::get('/route-tags/{id}', [RouteTagController::class, 'show']);
Route::get('/route-tags/slug/{slug}', [RouteTagController::class, 'showBySlug']);
Route::post('/route-tags/search', [RouteTagController::class, 'search']);

// ============================================
// PUBLIC PAYTABS ROUTES
// ============================================
Route::prefix('paytabs')->name('paytabs.')->group(function () {
    Route::post('/callback', [PayTabsController::class, 'callback'])->name('callback');
    Route::match(['GET', 'POST'], '/return', [PayTabsController::class, 'return'])->name('return');
    Route::post('/webhook', [PayTabsController::class, 'webhook'])->name('webhook');
    Route::match(['GET', 'POST'], '/success', [PayTabsController::class, 'paymentSuccess'])->name('success');
    Route::match(['GET', 'POST'], '/error', [PayTabsController::class, 'paymentError'])->name('error');
    Route::match(['GET', 'POST'], '/pending', [PayTabsController::class, 'paymentPending'])->name('pending');
    Route::match(['GET', 'POST'], '/cancel', [PayTabsController::class, 'paymentCancel'])->name('cancel');
    Route::post('verify-and-publish', [PayTabsController::class, 'verifyAndPublish']);
    Route::post('/test-callback', [PayTabsController::class, 'testCallback']);
});

Route::prefix('payment')->name('payment.')->group(function () {
    Route::match(['GET', 'POST'], '/success', [PayTabsController::class, 'paymentSuccess'])->name('success');
    Route::match(['GET', 'POST'], '/error', [PayTabsController::class, 'paymentError'])->name('error');
    Route::match(['GET', 'POST'], '/pending', [PayTabsController::class, 'paymentPending'])->name('pending');
});

// ============================================
// PUBLIC ROLES (Read Only)
// ============================================
Route::apiResource('roles', RoleController::class);

// ============================================
// PUBLIC CURRENCY RATES
// ============================================
Route::apiResource('currency-rates', CurrencyExchangeRateController::class);

// ============================================
// PUBLIC CARD TYPES
// ============================================
Route::apiResource('card-types', CardTypeController::class);

// ============================================
// TEST ROUTES
// ============================================
Route::get('/test-email', [SoomController::class, 'testEmail']);

// ============================================
// ============================================
// AUTHENTICATED ROUTES
// ============================================
// ============================================

Route::middleware('auth:api')->group(function () {

    // ============================================
    // AUTH MANAGEMENT
    // ============================================
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/user/update', [AuthController::class, 'updateProfile']);
        Route::put('/user/two-factor-toggle', [AuthController::class, 'toggleTwoFactor']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
    });

    // ============================================
    // USER MANAGEMENT
    // ============================================
    Route::prefix('admin/users')->group(function () {
        // List & Statistics
        Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::get('/stats', [UserController::class, 'stats'])->middleware('permission:users.view');
        Route::get('/stats/detailed', [UserController::class, 'detailedStats'])->middleware('permission:users.view');
        Route::get('/trashed', [UserController::class, 'getTrashed'])->middleware('permission:users.view');
        Route::get('/export', [UserController::class, 'export'])->middleware('permission:users.view');

        // Search
        Route::post('/search', [UserController::class, 'search'])->middleware('permission:users.view');

        // Bulk Actions
        Route::post('/bulk-action', [UserController::class, 'bulkAction'])->middleware('permission:users.delete');

        // CRUD Operations
        Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');
        Route::get('/{id}', [UserController::class, 'show'])->middleware('permission:users.view');
        Route::put('/{id}', [UserController::class, 'update'])->middleware('permission:users.update');
        Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');

        // User Account Management
        Route::post('/{id}/activate', [UserController::class, 'activate'])->middleware('permission:users.update');
        Route::post('/{id}/deactivate', [UserController::class, 'deactivate'])->middleware('permission:users.update');
        Route::post('/{id}/verify', [UserController::class, 'verifyUser'])->middleware('permission:users.update');
        Route::post('/{id}/restore', [UserController::class, 'restore'])->middleware('permission:users.update');
        Route::delete('/{id}/force-delete', [UserController::class, 'forceDelete'])->middleware('permission:users.delete');
        Route::post('{id}/toggle-verified', [UserController::class, 'toggleVerified'])->middleware('permission:users.update');
        Route::post('{id}/toggle-active', [UserController::class, 'toggleActive'])->middleware('permission:users.update');
        Route::patch('/{id}/activate', [UserController::class, 'activateUser']);
        Route::patch('/{id}/deactivate', [UserController::class, 'deactivateUser']);

        // Password Management
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.update');
        Route::put('/{id}/change-password', [UserController::class, 'changePassword'])->middleware('permission:users.update');

        // Profile Management
        Route::post('/{id}/profile-picture', [UserController::class, 'updateProfilePicture'])->middleware('permission:users.update');
        Route::put('/{id}/last-login', [UserController::class, 'updateLastLogin'])->middleware('permission:users.update');
        Route::put('/{id}/online-status', [UserController::class, 'updateOnlineStatus'])->middleware('permission:users.update');

        // Two-Factor Authentication
        Route::post('/{id}/two-factor/enable', [UserController::class, 'enableTwoFactor'])->middleware('permission:users.update');
        Route::post('/{id}/two-factor/disable', [UserController::class, 'disableTwoFactor'])->middleware('permission:users.update');

        // User Relations
        Route::get('/{id}/wishlists', [UserController::class, 'getUserWishlists'])->middleware('permission:users.view');
        Route::get('/{id}/listings', [UserController::class, 'getUserListings'])->middleware('permission:users.view');
        Route::get('/{id}/bank-cards', [UserController::class, 'getUserBankCards'])->middleware('permission:users.view');
        Route::get('/{id}/auction-history', [UserController::class, 'getUserAuctionHistory'])->middleware('permission:users.view');
    });

    // ============================================
    // PRICING RULES
    // ============================================
    Route::prefix('admin/pricing-rules-motorcycle')->group(function () {
        Route::get('/', [PricingRulesMotorcycleController::class, 'index'])->middleware('permission:pricing-rules-motorcycle.view');
        Route::post('/', [PricingRulesMotorcycleController::class, 'store'])->middleware('permission:pricing-rules-motorcycle.create');
        Route::get('/{pricingRulesMotorcycle}', [PricingRulesMotorcycleController::class, 'show'])->middleware('permission:pricing-rules-motorcycle.view');
        Route::put('/{pricingRulesMotorcycle}', [PricingRulesMotorcycleController::class, 'update'])->middleware('permission:pricing-rules-motorcycle.update');
        Route::delete('/{pricingRulesMotorcycle}', [PricingRulesMotorcycleController::class, 'destroy'])->middleware('permission:pricing-rules-motorcycle.delete');
    });

    Route::prefix('admin/pricing-rules-sparepart')->group(function () {
        Route::get('/', [PricingRulesSparepartController::class, 'index'])->middleware('permission:pricing-rules-sparepart.view');
        Route::post('/', [PricingRulesSparepartController::class, 'store'])->middleware('permission:pricing-rules-sparepart.create');
        Route::get('/{pricingRulesSparepart}', [PricingRulesSparepartController::class, 'show'])->middleware('permission:pricing-rules-sparepart.view');
        Route::put('/{pricingRulesSparepart}', [PricingRulesSparepartController::class, 'update'])->middleware('permission:pricing-rules-sparepart.update');
        Route::delete('/{pricingRulesSparepart}', [PricingRulesSparepartController::class, 'destroy'])->middleware('permission:pricing-rules-sparepart.delete');
    });

    Route::prefix('admin/pricing-rules-licence-plate')->group(function () {
        Route::get('/', [PricingRulesLicencePlateController::class, 'show'])->middleware('permission:pricing-rules-licence-plate.view');
        Route::put('/', [PricingRulesLicencePlateController::class, 'update'])->middleware('permission:pricing-rules-licence-plate.update');
    });

    // ============================================
    // PERMISSIONS & ROLES
    // ============================================
    Route::prefix('admin')->group(function () {
        Route::prefix('permissions')->group(function () {
            Route::get('/', [PermissionController::class, 'index'])->middleware('permission:permissions.view');
            Route::post('/', [PermissionController::class, 'store'])->middleware('permission:permissions.create');
            Route::get('/grouped', [PermissionController::class, 'grouped'])->middleware('permission:permissions.view');
            Route::get('/{id}', [PermissionController::class, 'show'])->middleware('permission:permissions.view');
            Route::put('/{id}', [PermissionController::class, 'update'])->middleware('permission:permissions.update');
            Route::delete('/{id}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.delete');
        });

        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->middleware('permission:roles.view');
            Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create');
            Route::get('/{id}', [RoleController::class, 'show'])->middleware('permission:roles.view');
            Route::put('/{id}', [RoleController::class, 'update'])->middleware('permission:roles.update');
            Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');
        });

        Route::prefix('roles/{roleId}/permissions')->group(function () {
            Route::get('/', [RolePermissionController::class, 'index'])->middleware('permission:roles.view');
            Route::post('/', [RolePermissionController::class, 'store'])->middleware('permission:roles.update');
            Route::post('/sync', [RolePermissionController::class, 'sync'])->middleware('permission:roles.update');
            Route::delete('/{permissionId}', [RolePermissionController::class, 'destroy'])->middleware('permission:roles.update');
        });
    });

    // ============================================
    // LISTINGS MANAGEMENT
    // ============================================
    Route::prefix('listings')->group(function () {
        Route::get('/my-ads', [ListingController::class, 'getMyAds']);
        Route::get('/draft', [ListingController::class, 'getDraftListings']);
        Route::get('/draft/{id}', [ListingController::class, 'getDraftListingById']);
        Route::delete('/draft/{id}', [ListingController::class, 'deleteDraftListingById']);
        Route::post('/', [ListingController::class, 'store']);
        Route::put('/complete/{id}', [ListingController::class, 'completeListing']);
        Route::get('/my-listing', [ListingController::class, 'my_listing']);
        Route::get('/{listingId}/payment-status', [ListingController::class, 'checkListingPaymentStatus']);
    });

    Route::get('pricing', [ListingController::class, 'getPriceByModelId']);

    // Listing with Auction
    Route::post('/listing-with-auction', [ListingAuctionController::class, 'store']);

    // Auctions
    Route::prefix('auctions')->group(function () {
        Route::get('/', [ListingAuctionController::class, 'auctionsIndex']);
        Route::post('/', [ListingAuctionController::class, 'auctionsStore']);
        Route::get('/{id}', [ListingAuctionController::class, 'auctionsShow']);
        Route::put('/{id}', [ListingAuctionController::class, 'auctionsUpdate']);
        Route::delete('/{id}', [ListingAuctionController::class, 'auctionsDestroy']);
        Route::get('/my', [ListingAuctionController::class, 'myAuctions']);
    });

    // Admin Listing Management
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::post('/listings/republish-paid', [ListingController::class, 'checkAndRepublishPaidListings']);
        Route::get('/listings/payment-stats', [ListingController::class, 'getListingPaymentStats']);
        Route::post('/payments/{paymentId}/force-verify', [ListingController::class, 'forcePaymentVerification']);
    });

    // ============================================
    // LICENSE PLATES
    // ============================================
    Route::post('/license-plates', [LicensePlateController::class, 'store']);
    Route::post('/plate-formats', [PlateFormatController::class, 'store']);

    // ============================================
    // WISHLISTS
    // ============================================
    Route::prefix('wishlists')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/', [WishlistController::class, 'store']);
        Route::delete('/{listing_id}', [WishlistController::class, 'destroy']);
    });

    // ============================================
    // BANK CARDS
    // ============================================
    Route::apiResource('BankCards', CardController::class);
    Route::get('my-cards', [CardController::class, 'myCards']);
    Route::post('my-cards', [CardController::class, 'addMyCard']);
    Route::put('my-cards/{id}', [CardController::class, 'editMyCard']);
    Route::delete('my-cards/{id}', [CardController::class, 'deleteMyCard']);
    Route::patch('my-cards/{id}/set-default', [CardController::class, 'setAsDefault']);

    // ============================================
    // MY GARAGE
    // ============================================
    Route::prefix('my-garage')->group(function () {
        Route::get('/', [MyGarageController::class, 'index']);
        Route::post('/', [MyGarageController::class, 'store']);
        Route::get('/default', [MyGarageController::class, 'getDefault']);
        Route::get('/{id}', [MyGarageController::class, 'show']);
        Route::put('/{id}', [MyGarageController::class, 'update']);
        Route::patch('/{id}', [MyGarageController::class, 'update']);
        Route::delete('/{id}', [MyGarageController::class, 'destroy']);
        Route::post('/{id}/set-default', [MyGarageController::class, 'setDefault']);
    });

    Route::get('motorcycle-data', [MyGarageController::class, 'getMotorcycleData']);

    // ============================================
    // SOOM (BIDDING SYSTEM)
    // ============================================
    Route::prefix('sooms')->group(function () {
        Route::get('/max', [SoomController::class, 'getMaxSoom']);
        Route::get('/max/me', [SoomController::class, 'getMyMaxSoom']);
        Route::get('/overbidding/users', [SoomController::class, 'getUsersWithOverbidding']);
    });

    Route::prefix('listings/{listingId}')->group(function () {
        Route::get('/sooms', [SoomController::class, 'getListingSooms']);
        Route::get('/minimum-soom', [SoomController::class, 'getMinimumSoomAmount']);
        Route::get('/last-soom', [SoomController::class, 'getLastSoom']);
        Route::post('/soom', [SoomController::class, 'createSoom']);
        Route::patch('/mark-as-sold', [SoomController::class, 'markListingAsSold']);
        Route::patch('/close', [SoomController::class, 'closeListing']);
        Route::patch('/reopen', [SoomController::class, 'reopenListing']);
    });

    Route::prefix('submissions')->group(function () {
        Route::patch('/{submissionId}/accept', [SoomController::class, 'acceptSoom']);
        Route::patch('/{submissionId}/reject', [SoomController::class, 'rejectSoom']);
        Route::post('/{submissionId}/validate-sale', [SoomController::class, 'validateSale']);
        Route::put('/{submissionId}/edit', [SoomController::class, 'editSoom']);
        Route::delete('/{submissionId}/cancel', [SoomController::class, 'cancelSoom']);
    });

    Route::get('/my-listings-sooms', [SoomController::class, 'getMyListingsSooms']);
    Route::get('/my-sooms', [SoomController::class, 'getMySooms']);
    Route::get('/validated-sales', [SoomController::class, 'getValidatedSales']);
    Route::get('/pending-validations', [SoomController::class, 'getPendingValidations']);
    Route::get('/soom-stats', [SoomController::class, 'getSoomStats']);

    // ============================================
    // PROMO CODES
    // ============================================
    Route::post('promo/check', [PromoCodeController::class, 'checkPromo']);

    // ============================================
    // IMAGE UPLOAD
    // ============================================
    Route::post('/upload-image', [ImageUploadController::class, 'upload']);

    // ============================================
    // PAYMENT HISTORY
    // ============================================
    Route::prefix('payments')->group(function () {
        Route::get('/history/user', [PaymentHistoryController::class, 'historyPaymentByUser']);
        Route::get('/history/global', [PaymentHistoryController::class, 'historyPaymentGlobal']);
        Route::get('/{id}', [PaymentHistoryController::class, 'show']);
        Route::get('/stats/user', [PaymentHistoryController::class, 'userStats']);
    });

    // ============================================
    // PAYTABS AUTHENTICATED
    // ============================================
    Route::prefix('paytabs')->name('paytabs.')->group(function () {
        Route::post('create', [PayTabsController::class, 'createPayment'])->name('create');
    });

    Route::prefix('listings')->name('api.listings.')->group(function () {
        Route::post('{listing}/payment', [PayTabsController::class, 'initiatePayment'])->name('payment');
    });

    Route::prefix('payments')->name('api.payments.')->group(function () {
        Route::get('{payment}/status', [PayTabsController::class, 'checkPaymentStatus'])->name('status');
        Route::get('history', [PayTabsController::class, 'getPaymentHistory'])->name('history');
        Route::get('{payment}', [PayTabsController::class, 'getPaymentDetails'])->name('details');
    });

    // ============================================
    // GUIDES - AUTHENTICATED
    // ============================================
    Route::prefix('guides')->group(function () {
        // Categories
        Route::prefix('categories')->group(function () {
            Route::post('/', [GuideCategoryController::class, 'store']);
            Route::put('/{id}', [GuideCategoryController::class, 'update']);
            Route::delete('/{id}', [GuideCategoryController::class, 'destroy']);
        });

        // Tags
        Route::prefix('tags')->group(function () {
            Route::post('/', [GuideTagController::class, 'store']);
            Route::put('/{id}', [GuideTagController::class, 'update']);
            Route::delete('/{id}', [GuideTagController::class, 'destroy']);
        });

        // Guides Management
        Route::get('/my/guides', [GuideController::class, 'myGuides']);
        Route::post('/', [GuideController::class, 'store']);
        Route::put('/{id}', [GuideController::class, 'update']);
        Route::delete('/{id}', [GuideController::class, 'destroy']);
        Route::post('/{id}/publish', [GuideController::class, 'publish']);
        Route::post('/{id}/archive', [GuideController::class, 'archive']);

        // Guide Images
        Route::prefix('{guide_id}/images')->group(function () {
            Route::get('/', [GuideImageController::class, 'index']);
            Route::post('/', [GuideImageController::class, 'store']);
            Route::put('/{id}', [GuideImageController::class, 'update']);
            Route::delete('/{id}', [GuideImageController::class, 'destroy']);
            Route::post('/reorder', [GuideImageController::class, 'reorder']);
        });

        // Likes
        Route::post('/{id}/like', [GuideLikeController::class, 'like']);
        Route::delete('/{id}/unlike', [GuideLikeController::class, 'unlike']);
        Route::post('/{id}/toggle-like', [GuideLikeController::class, 'toggleLike']);
        Route::get('/my/liked', [GuideLikeController::class, 'myLikedGuides']);
        Route::get('/{id}/likes', [GuideLikeController::class, 'getGuideLikes']);

        // Bookmarks
        Route::post('/{id}/bookmark', [GuideBookmarkController::class, 'bookmark']);
        Route::delete('/{id}/unbookmark', [GuideBookmarkController::class, 'unbookmark']);
        Route::post('/{id}/toggle-bookmark', [GuideBookmarkController::class, 'toggleBookmark']);
        Route::get('/my/bookmarks', [GuideBookmarkController::class, 'myBookmarks']);
        Route::get('/my/bookmarks/count', [GuideBookmarkController::class, 'countMyBookmarks']);
        Route::delete('/my/bookmarks/clear', [GuideBookmarkController::class, 'clearAllBookmarks']);
        Route::get('/{id}/bookmark-status', [GuideBookmarkController::class, 'checkBookmarkStatus']);
        Route::post('/bookmarks/batch', [GuideBookmarkController::class, 'batchCheckBookmarks']);

        // Comments
        Route::post('/{id}/comments', [GuideCommentController::class, 'store']);
        Route::put('/comments/{commentId}', [GuideCommentController::class, 'update']);
        Route::delete('/comments/{commentId}', [GuideCommentController::class, 'destroy']);
        Route::get('/my/comments', [GuideCommentController::class, 'myComments']);
        Route::post('/comments/{commentId}/approve', [GuideCommentController::class, 'approve']);
        Route::post('/comments/{commentId}/reject', [GuideCommentController::class, 'reject']);
        Route::get('/comments/pending', [GuideCommentController::class, 'pending']);
    });

    // ============================================
    // EVENTS - AUTHENTICATED
    // ============================================
    Route::prefix('event-categories')->group(function () {
        Route::post('/', [EventCategoryController::class, 'store']);
        Route::put('/{id}', [EventCategoryController::class, 'update']);
        Route::delete('/{id}', [EventCategoryController::class, 'destroy']);
    });

    Route::prefix('events')->group(function () {
        // Event CRUD
        Route::post('/', [EventController::class, 'store']);
        Route::put('/{id}', [EventController::class, 'update']);
        Route::delete('/{id}', [EventController::class, 'destroy']);
        Route::get('/{id}/statistics', [EventController::class, 'statistics']);
        Route::post('/{id}/publish', [EventController::class, 'togglePublish']);

        // Registration & Participants
        Route::post('/{eventId}/register', [EventParticipantController::class, 'register']);
        Route::delete('/{eventId}/unregister', [EventParticipantController::class, 'unregister']);
        Route::put('/{eventId}/participants/{participantId}/confirm', [EventParticipantController::class, 'confirm']);
        Route::put('/{eventId}/participants/{participantId}/check-in', [EventParticipantController::class, 'checkIn']);
        Route::get('/{eventId}/participants/statistics', [EventParticipantController::class, 'statistics']);
        Route::get('/{eventId}/participants/{participantId}', [EventParticipantController::class, 'show']);
        Route::get('/{eventId}/my-registration', [EventParticipantController::class, 'myRegistration']);

        // Reviews
        Route::post('/{eventId}/reviews', [EventReviewController::class, 'store']);
        Route::put('/{eventId}/reviews/{reviewId}', [EventReviewController::class, 'update']);
        Route::delete('/{eventId}/reviews/{reviewId}', [EventReviewController::class, 'destroy']);
        Route::get('/{eventId}/my-review', [EventReviewController::class, 'myReview']);
        Route::get('/{eventId}/reviews/can-review', [EventReviewController::class, 'canReview']);

        // Favorites
        Route::post('/{eventId}/favorite', [EventFavoriteController::class, 'store']);
        Route::delete('/{eventId}/unfavorite', [EventFavoriteController::class, 'destroy']);
        Route::get('/{eventId}/is-favorite', [EventFavoriteController::class, 'isFavorite']);
        Route::post('/{eventId}/toggle-favorite', [EventFavoriteController::class, 'toggle']);

        // Activities
        Route::post('/{eventId}/activities', [EventActivityController::class, 'store']);
        Route::put('/{eventId}/activities/{activityId}', [EventActivityController::class, 'update']);
        Route::delete('/{eventId}/activities/{activityId}', [EventActivityController::class, 'destroy']);

        // Tickets
        Route::post('/{eventId}/tickets', [EventTicketController::class, 'store']);
        Route::put('/{eventId}/tickets/{ticketId}', [EventTicketController::class, 'update']);
        Route::delete('/{eventId}/tickets/{ticketId}', [EventTicketController::class, 'destroy']);
        Route::post('/{eventId}/tickets/{ticketId}/purchase', [EventTicketController::class, 'purchase']);
        Route::get('/{eventId}/tickets/statistics', [EventTicketController::class, 'statistics']);
        Route::get('/{eventId}/tickets/purchases', [EventTicketController::class, 'eventPurchases']);
        Route::post('/{eventId}/tickets/{ticketId}/toggle-active', [EventTicketController::class, 'toggleActive']);

        // Contacts
        Route::post('/{eventId}/contacts', [EventContactController::class, 'store']);
        Route::put('/{eventId}/contacts/{contactId}', [EventContactController::class, 'update']);
        Route::delete('/{eventId}/contacts/{contactId}', [EventContactController::class, 'destroy']);

        // FAQs
        Route::post('/{eventId}/faqs', [EventFaqController::class, 'store']);
        Route::put('/{eventId}/faqs/{faqId}', [EventFaqController::class, 'update']);
        Route::delete('/{eventId}/faqs/{faqId}', [EventFaqController::class, 'destroy']);
        Route::post('/{eventId}/faqs/reorder', [EventFaqController::class, 'reorder']);
        Route::delete('/{eventId}/faqs/bulk-delete', [EventFaqController::class, 'bulkDelete']);
        Route::get('/{eventId}/faqs/search', [EventFaqController::class, 'search']);

        // Updates
        Route::post('/{eventId}/updates', [EventUpdateController::class, 'store']);
        Route::put('/{eventId}/updates/{updateId}', [EventUpdateController::class, 'update']);
        Route::delete('/{eventId}/updates/{updateId}', [EventUpdateController::class, 'destroy']);
        Route::delete('/{eventId}/updates/bulk-delete', [EventUpdateController::class, 'bulkDelete']);

        // Sponsors
        Route::get('/{eventId}/sponsors', [EventSponsorController::class, 'eventSponsors']);
        Route::post('/{eventId}/sponsors/{sponsorId}/attach', [EventSponsorController::class, 'attachToEvent']);
        Route::delete('/{eventId}/sponsors/{sponsorId}/detach', [EventSponsorController::class, 'detachFromEvent']);
        Route::put('/{eventId}/sponsors/{sponsorId}/update-level', [EventSponsorController::class, 'updateSponsorLevel']);
    });

    // Tickets Management
    Route::prefix('tickets')->group(function () {
        Route::get('/{purchaseId}', [EventTicketController::class, 'showPurchase']);
        Route::post('/{purchaseId}/check-in', [EventTicketController::class, 'checkIn']);
        Route::get('/verify/{qrCode}', [EventTicketController::class, 'verifyQRCode']);
    });

    // User Event Routes
    Route::get('/my-events', [EventParticipantController::class, 'myEvents']);
    Route::get('/my-favorite-events', [EventFavoriteController::class, 'myFavorites']);
    Route::delete('/my-favorite-events/clear', [EventFavoriteController::class, 'clearAll']);
    Route::get('/my-favorite-events/count', [EventFavoriteController::class, 'count']);
    Route::get('/my-tickets', [EventTicketController::class, 'myTickets']);
    Route::get('/my-organized-events', [EventController::class, 'myOrganizedEvents']);
    Route::get('/my-reviews', [EventReviewController::class, 'myReviews']);
    Route::get('/my-event-updates', [EventUpdateController::class, 'myEventUpdates']);

    // Event Sponsors
    Route::prefix('event-sponsors')->group(function () {
        Route::get('/', [EventSponsorController::class, 'index']);
        Route::post('/', [EventSponsorController::class, 'store']);
        Route::get('/{id}', [EventSponsorController::class, 'show']);
        Route::put('/{id}', [EventSponsorController::class, 'update']);
        Route::delete('/{id}', [EventSponsorController::class, 'destroy']);
    });

    // ============================================
    // NEWSLETTER - AUTHENTICATED
    // ============================================
    Route::prefix('newsletter')->group(function () {
        Route::get('/preferences', [NewsletterController::class, 'getPreferences']);
        Route::put('/preferences', [NewsletterController::class, 'updatePreferences']);

        // Campaigns (Admin)
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
    // POI - AUTHENTICATED
    // ============================================
    Route::prefix('poi-types')->group(function () {
        Route::post('/', [PoiTypeController::class, 'store']);
        Route::put('/{id}', [PoiTypeController::class, 'update']);
        Route::patch('/{id}', [PoiTypeController::class, 'update']);
        Route::delete('/{id}', [PoiTypeController::class, 'destroy']);
    });

    Route::prefix('poi-services')->group(function () {
        Route::post('/', [PoiServiceController::class, 'store']);
        Route::put('/{id}', [PoiServiceController::class, 'update']);
        Route::delete('/{id}', [PoiServiceController::class, 'destroy']);
    });

    Route::prefix('pois')->group(function () {
        Route::get('/', [PointOfInterestController::class, 'index']);
        Route::post('/', [PointOfInterestController::class, 'store']);
        Route::get('/{id}', [PointOfInterestController::class, 'show']);
        Route::put('/{id}', [PointOfInterestController::class, 'update']);
        Route::delete('/{id}', [PointOfInterestController::class, 'destroy']);
        Route::post('/{id}/favorite', [PointOfInterestController::class, 'toggleFavorite']);
        Route::get('/nearby', [PointOfInterestController::class, 'nearby']);
        Route::get('/favorites', [PointOfInterestController::class, 'favorites']);

        // Reviews
        Route::get('/{poi_id}/reviews', [PoiReviewController::class, 'index']);
        Route::post('/{poi_id}/reviews', [PoiReviewController::class, 'store']);
        Route::put('/{poi_id}/reviews/{id}', [PoiReviewController::class, 'update']);
        Route::delete('/{poi_id}/reviews/{id}', [PoiReviewController::class, 'destroy']);

        // Reports
        Route::get('/{poi_id}/reports', [PoiReportController::class, 'index']);
        Route::post('/{poi_id}/reports', [PoiReportController::class, 'store']);
        Route::get('/{poi_id}/reports/{id}', [PoiReportController::class, 'show']);
        Route::put('/{poi_id}/reports/{id}/status', [PoiReportController::class, 'updateStatus']);
        Route::delete('/{poi_id}/reports/{id}', [PoiReportController::class, 'destroy']);
    });

    Route::get('/reports/pending', [PoiReportController::class, 'pending']);
    Route::get('/reports/stats', [PoiReportController::class, 'stats']);
    Route::get('/user/reports', [PoiReportController::class, 'userReports']);

    // ============================================
    // ROUTES - AUTHENTICATED
    // ============================================
    Route::prefix('route-categories')->group(function () {
        Route::post('/', [RouteCategoryController::class, 'store']);
        Route::put('/{id}', [RouteCategoryController::class, 'update']);
        Route::delete('/{id}', [RouteCategoryController::class, 'destroy']);
    });

    Route::prefix('route-tags')->group(function () {
        Route::post('/', [RouteTagController::class, 'store']);
        Route::put('/{id}', [RouteTagController::class, 'update']);
        Route::delete('/{id}', [RouteTagController::class, 'destroy']);
    });

    Route::prefix('routes')->group(function () {
        Route::get('/', [RouteController::class, 'index']);
        Route::post('/', [RouteController::class, 'store']);
        Route::get('/{id}', [RouteController::class, 'show']);
        Route::put('/{id}', [RouteController::class, 'update']);
        Route::delete('/{id}', [RouteController::class, 'destroy']);
        Route::post('/{id}/like', [RouteController::class, 'toggleLike']);
        Route::post('/{id}/favorite', [RouteController::class, 'toggleFavorite']);

        // Waypoints
        Route::get('/{route_id}/waypoints', [RouteWaypointController::class, 'index']);
        Route::post('/{route_id}/waypoints', [RouteWaypointController::class, 'store']);
        Route::get('/{route_id}/waypoints/{id}', [RouteWaypointController::class, 'show']);
        Route::put('/{route_id}/waypoints/{id}', [RouteWaypointController::class, 'update']);
        Route::delete('/{route_id}/waypoints/{id}', [RouteWaypointController::class, 'destroy']);
        Route::put('/{route_id}/waypoints/{id}/reorder', [RouteWaypointController::class, 'reorder']);

        // Reviews
        Route::get('/{route_id}/reviews', [RouteReviewController::class, 'index']);
        Route::post('/{route_id}/reviews', [RouteReviewController::class, 'store']);
        Route::delete('/{route_id}/reviews/{id}', [RouteReviewController::class, 'destroy']);

        // Warnings
        Route::get('/{route_id}/warnings', [RouteWarningController::class, 'index']);
        Route::post('/{route_id}/warnings', [RouteWarningController::class, 'store']);
        Route::get('/{route_id}/warnings/{id}', [RouteWarningController::class, 'show']);
        Route::put('/{route_id}/warnings/{id}', [RouteWarningController::class, 'update']);
        Route::delete('/{route_id}/warnings/{id}', [RouteWarningController::class, 'destroy']);
        Route::put('/{route_id}/warnings/{id}/deactivate', [RouteWarningController::class, 'deactivate']);

        // Completions
        Route::get('/{route_id}/completions', [RouteCompletionController::class, 'index']);
        Route::post('/{route_id}/completions', [RouteCompletionController::class, 'store']);
        Route::get('/{route_id}/completions/{id}', [RouteCompletionController::class, 'show']);
        Route::put('/{route_id}/completions/{id}', [RouteCompletionController::class, 'update']);
        Route::delete('/{route_id}/completions/{id}', [RouteCompletionController::class, 'destroy']);
        Route::get('/{route_id}/check-completion', [RouteCompletionController::class, 'checkCompletion']);
    });

    Route::get('/warnings/active', [RouteWarningController::class, 'getAllActive']);
    Route::get('/user/completions', [RouteCompletionController::class, 'userCompletions']);
    Route::get('/user/completion-stats', [RouteCompletionController::class, 'userStats']);
});
