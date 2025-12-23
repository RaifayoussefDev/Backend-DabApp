<?php

use Illuminate\Support\Facades\Route;

// ============================================
// CONTROLLERS IMPORTS
// ============================================
use App\Http\Controllers\{
    AdminMenuController,
    AuthController,
    BannerController,
    BikePartBrandController,
    BikePartCategoryController,
    CardController,
    CardTypeController,
    CurrencyExchangeRateController,
    DashboardController,
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
    ListingAuctionController,
    ListingController,
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
    UserController,
    WhatsAppOtpController,
    WishlistController,
    AuthAdminController,
    EmailNotificationController,
    NotificationController,
    NotificationPreferenceController,
};
use App\Http\Controllerss\NotificationPreferenceController as ControllerssNotificationPreferenceController;
use PhpOffice\PhpSpreadsheet\Reader\Xls\RC4;

// ============================================
// ============================================
// PUBLIC ROUTES (NO AUTHENTICATION)
// ============================================
// ============================================

// ============================================
// AUTHENTICATION (PUBLIC)
// ============================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/send-otp', [WhatsAppOtpController::class, 'sendOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/resend-otp-email', [AuthController::class, 'resendOtpEmail']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/firebase-login', [FirebaseAuthController::class, 'loginWithFirebase']);
Route::get('/get-country', [AuthController::class, 'getCountry'])->name('get.country');
Route::get('/countries', [AuthController::class, 'getAllCountries']);


Route::prefix('admin')->group(function () {

    // ============================================
    // PUBLIC ADMIN ROUTES (sans authentification)
    // ============================================
    Route::post('/login', [AuthAdminController::class, 'login'])->name('admin.login');
    Route::post('/verify-otp', [AuthAdminController::class, 'verifyOtp']);
    Route::post('/refresh', [AuthAdminController::class, 'refresh']);
    Route::post('/send-otp', [WhatsAppOtpController::class, 'sendOtp']);
    Route::post('/resend-otp', [AuthAdminController::class, 'resendOtp']);
    Route::post('/resend-otp-email', [AuthAdminController::class, 'resendOtpEmail']);
    Route::post('/forgot-password', [AuthAdminController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthAdminController::class, 'resetPassword']);

    // ============================================
    // PROTECTED ADMIN ROUTES (authentification requise)
    // ============================================
    Route::middleware(['auth.admin'])->group(function () {
        // Authentication & Session
        Route::get('/me', [AuthAdminController::class, 'me']);
        Route::post('/logout', [AuthAdminController::class, 'logout']);

        // Profile Management
        Route::put('/user/update', [AuthAdminController::class, 'updateProfile']);
        Route::put('/user/two-factor-toggle', [AuthAdminController::class, 'toggleTwoFactor']);
        Route::put('/change-password', [AuthAdminController::class, 'changePassword']); // ✅ Changé de AuthController à AuthAdminController


    });
});

// ============================================
// LOCATIONS (PUBLIC)
// ============================================
Route::get('/locations', [LocationController::class, 'index']);
Route::post('/countries', [LocationController::class, 'storeCountry']);
Route::put('/countries/{id}', [LocationController::class, 'updateCountry']);
Route::delete('/countries/{id}', [LocationController::class, 'destroyCountry']);
Route::post('/cities', [LocationController::class, 'storeCity']);
Route::put('/cities/{id}', [LocationController::class, 'updateCity']);
Route::delete('/cities/{id}', [LocationController::class, 'destroyCity']);

// ============================================
// MOTORCYCLES (PUBLIC)
// ============================================
Route::apiResource('motorcycle-types', MotorcycleTypeController::class);
Route::apiResource('motorcycle-brands', MotorcycleBrandController::class);
Route::apiResource('motorcycle-models', MotorcycleModelController::class);
Route::apiResource('motorcycle-years', MotorcycleYearController::class);
Route::apiResource('motorcycles', MotorcycleController::class);
Route::post('/motorcycles/import', [MotorcycleController::class, 'importMotorcycles']);
Route::post('/motorcycles/import', [MotorcycleImportController::class, 'import']);

Route::prefix('motorcycle')->group(function () {
    Route::get('/brands', [MotorcycleFilterController::class, 'getBrands']);
    Route::get('/brands/all', [MotorcycleFilterController::class, 'getAllBrands']);
    Route::get('/models/{brandId}', [MotorcycleFilterController::class, 'getModelsByBrand'])->where('brandId', '[0-9]+');
    Route::get('/years/{modelId}', [MotorcycleFilterController::class, 'getYearsByModel'])->where('modelId', '[0-9]+');
    Route::get('/details/{yearId}', [MotorcycleFilterController::class, 'getDetailsByYear'])->where('yearId', '[0-9]+');
    Route::get('/brand/{brandId}', [MotorcycleFilterController::class, 'getByBrand']);
    Route::get('/year/{yearId}', [MotorcycleFilterController::class, 'getByYear']);
    Route::post('/clear-cache', [MotorcycleFilterController::class, 'clearCache']);
});

// ============================================
// MOTORCYCLE COMPARISON (PUBLIC)
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
// LISTINGS (PUBLIC)
// ============================================
Route::get('/listings/by-category/{category_id}', [ListingController::class, 'getByCategory']);
Route::get('/listings/search-by-model', [ListingController::class, 'searchByCategoryAndModel']);
Route::get('/recent', [ListingController::class, 'getRecentListings']);
Route::get('/types', [ListingController::class, 'getAllTypes']);

// Listings Statistics
Route::get('/brands/listings-count', [ListingController::class, 'getBrandsWithListingCount']);
Route::get('/brands/{brandId}/models-with-listings', [ListingController::class, 'getModelsWithListingsByBrand']);
Route::get('/brands/{brandId}/models/{modelId}/years-with-listings', [ListingController::class, 'getYearsWithListingsByBrandAndModel']);
Route::get('/categorie/listings-count', [ListingController::class, 'getTypesWithListingCount']);
Route::get('/categories/{categoryId}/price-range', [ListingController::class, 'getPriceRangeByCategory'])->where('categoryId', '[1-3]');
Route::get('/bike-part-categories', [ListingController::class, 'getBikePartCategoriesWithListingCount']);
Route::get('/bike-part-brands', [ListingController::class, 'getBikePartBrandsWithListingCount']);

// ============================================
// FILTERS (PUBLIC)
// ============================================
Route::get('/filter/motorcycles', [FilterController::class, 'filterMotorcycles']);
Route::get('/filter/spare-parts', [FilterController::class, 'filterSpareParts']);
Route::get('/filter/license-plates', [FilterController::class, 'filterLicensePlates']);
Route::get('/filter-options-license-plates', [FilterController::class, 'getLicensePlateFilterOptions']);
Route::get('/filter-license-plates', [FilterController::class, 'filterLicensePlates']);

// ============================================
// BIKE PARTS (PUBLIC)
// ============================================
Route::apiResource('bike-part-brands', BikePartBrandController::class);
Route::apiResource('bike-part-categories', BikePartCategoryController::class);

// ============================================
// LICENSE PLATES (PUBLIC)
// ============================================
Route::get('/license-plates', [LicensePlateController::class, 'index']);
Route::get('/license-plates/{id}', [LicensePlateController::class, 'show']);
Route::get('/license-plates/{id}/formatted', [LicensePlateController::class, 'showFormatted']);
Route::get('/cities/{cityId}/plate-formats', [LicensePlateController::class, 'getFormatsByCity']);
Route::get('/cities/{cityId}/plate-formats/details', [LicensePlateController::class, 'getFormatsByCityWithDetails']);
Route::get('/countries/{countryId}/plate-formats', [LicensePlateController::class, 'getFormatsByCountry']);

// ============================================
// PLATE GENERATOR (PUBLIC)
// ============================================
Route::post('/generate-plate', [PlateGeneratorController::class, 'generatePlate']);
Route::get('/download-plate/{filename}', [PlateGeneratorController::class, 'downloadPlate']);

// ============================================
// CURRENCY RATES (PUBLIC)
// ============================================
Route::apiResource('currency-rates', CurrencyExchangeRateController::class);

// ============================================
// CARD TYPES (PUBLIC)
// ============================================
Route::apiResource('card-types', CardTypeController::class);

// ============================================
// ROLES (PUBLIC - READ ONLY)
// ============================================
Route::apiResource('roles', RoleController::class);

// ============================================
// PAYTABS WEBHOOKS (PUBLIC)
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
// GUIDES (PUBLIC)
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
// EVENTS (PUBLIC)
// ============================================
Route::prefix('event-categories')->group(function () {
    Route::get('/', [EventCategoryController::class, 'index']);
    Route::get('/{id}', [EventCategoryController::class, 'show']);
    Route::get('/{id}/events', [EventCategoryController::class, 'events']);
});

Route::middleware('auth:api')->prefix('events')->group(function () {
    // Public info routes
    Route::get('/', [EventController::class, 'index']);
    Route::get('/upcoming', [EventController::class, 'upcoming']);
    Route::get('/featured', [EventController::class, 'featured']);
    Route::get('/my-interests', [EventController::class, 'myInterests']);

    Route::get('/{id}', [EventController::class, 'show']);

    // Interested users
    Route::get('/{id}/interested-users', [EventController::class, 'getInterestedUsers']);
    Route::post('/{id}/toggle-interest', [EventController::class, 'toggleInterest']);

    // Participants
    Route::get('/{eventId}/participants', [EventParticipantController::class, 'index']);

    // Reviews
    Route::get('/{eventId}/reviews', [EventReviewController::class, 'index']);
    Route::get('/{eventId}/reviews/{reviewId}', [EventReviewController::class, 'show']);

    // Activities
    Route::get('/{eventId}/activities', [EventActivityController::class, 'index']);
    Route::get('/{eventId}/activities/{activityId}', [EventActivityController::class, 'show']);

    // Tickets
    Route::get('/{eventId}/tickets', [EventTicketController::class, 'index']);
    Route::get('/{eventId}/tickets/{ticketId}', [EventTicketController::class, 'show']);

    // Contacts
    Route::get('/{eventId}/contacts', [EventContactController::class, 'index']);
    Route::get('/{eventId}/contacts/{contactId}', [EventContactController::class, 'show']);

    // FAQs
    Route::get('/{eventId}/faqs', [EventFaqController::class, 'index']);
    Route::get('/{eventId}/faqs/{faqId}', [EventFaqController::class, 'show']);

    // Updates
    Route::get('/{eventId}/updates', [EventUpdateController::class, 'index']);
    Route::get('/{eventId}/updates/{updateId}', [EventUpdateController::class, 'show']);
    Route::get('/{eventId}/updates/important', [EventUpdateController::class, 'important']);
    Route::get('/{eventId}/updates/latest', [EventUpdateController::class, 'latest']);
});

// ============================================
// NEWSLETTER (PUBLIC)
// ============================================
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
Route::post('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe']);

// ============================================
// POI (PUBLIC)
// ============================================
Route::get('/poi-types', [PoiTypeController::class, 'index']);
Route::get('/poi-types/stats', [PoiTypeController::class, 'stats']);
Route::get('/poi-types/{id}', [PoiTypeController::class, 'show']);
Route::get('/poi-types/{id}/pois', [PoiTypeController::class, 'getPois']);
Route::get('/poi-services', [PoiServiceController::class, 'index']);
Route::get('/poi-services/{id}', [PoiServiceController::class, 'show']);

// ============================================
// ROUTES (PUBLIC)
// ============================================
Route::get('/route-categories', [RouteCategoryController::class, 'index']);
Route::get('/route-categories/{id}', [RouteCategoryController::class, 'show']);
Route::get('/route-tags', [RouteTagController::class, 'index']);
Route::get('/route-tags/{id}', [RouteTagController::class, 'show']);
Route::get('/route-tags/slug/{slug}', [RouteTagController::class, 'showBySlug']);
Route::post('/route-tags/search', [RouteTagController::class, 'search']);

// ============================================
// TEST ROUTES
// ============================================
Route::get('/test-email', [SoomController::class, 'testEmail']);

// ============================================
// ============================================
// AUTHENTICATED ROUTES (JWT REQUIRED)
// ============================================
// ============================================
Route::get('/banners', [BannerController::class, 'index']);

Route::middleware('auth:api')->group(function () {
    Route::get('admin/dashboard', [DashboardController::class, 'index']);

    // ============================================
    // AUTH MANAGEMENT
    // ============================================
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/update', [AuthController::class, 'updateProfile']);
    Route::put('/user/two-factor-toggle', [AuthController::class, 'toggleTwoFactor']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);
    
    // Account Deletion
    Route::prefix('user')->group(function () {
        Route::post('/delete-request', [AuthController::class, 'deleteAccountRequest']);
        Route::post('/delete-confirm', [AuthController::class, 'confirmDeleteAccount']);
        Route::post('/delete-resend-otp', [AuthController::class, 'resendDeletionOtp']);
    });



    // ============================================
    // USERS MANAGEMENT (ADMIN)
    // ============================================
    Route::prefix('admin/users')->group(function () {

        Route::get('/stats', [UserController::class, 'stats'])->middleware('permission:users.view');
        Route::get('/stats/detailed', [UserController::class, 'detailedStats'])->middleware('permission:users.view');
        Route::get('/trashed', [UserController::class, 'getTrashed'])->middleware('permission:users.view');
        Route::get('/export', [UserController::class, 'export'])->middleware('permission:users.view');

        Route::get('/authentication-logs', [UserController::class, 'getAuthenticationLogs'])->middleware('permission:users.view');

        // Routes POST sans ID
        Route::post('/search', [UserController::class, 'search'])->middleware('permission:users.view');
        Route::post('/bulk-action', [UserController::class, 'bulkAction'])->middleware('permission:users.delete');

        // Routes de liste et création
        Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');

        // ⭐ ROUTES AVEC {id} - EN DERNIER
        Route::get('/{id}', [UserController::class, 'show'])->middleware('permission:users.view');
        Route::put('/{id}', [UserController::class, 'update'])->middleware('permission:users.update');
        Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');

        // Actions sur utilisateur spécifique
        Route::post('/{id}/activate', [UserController::class, 'activate'])->middleware('permission:users.update');
        Route::post('/{id}/deactivate', [UserController::class, 'deactivate'])->middleware('permission:users.update');
        Route::post('/{id}/verify', [UserController::class, 'verifyUser'])->middleware('permission:users.update');
        Route::post('/{id}/restore', [UserController::class, 'restore'])->middleware('permission:users.update');
        Route::delete('/{id}/force-delete', [UserController::class, 'forceDelete'])->middleware('permission:users.delete');
        Route::post('/{id}/toggle-verified', [UserController::class, 'toggleVerified'])->middleware('permission:users.update');
        Route::post('/{id}/toggle-active', [UserController::class, 'toggleActive'])->middleware('permission:users.update');

        // Password management
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.update');
        Route::put('/{id}/change-password', [UserController::class, 'changePassword'])->middleware('permission:users.update');

        // Profile management
        Route::post('/{id}/profile-picture', [UserController::class, 'updateProfilePicture'])->middleware('permission:users.update');
        Route::put('/{id}/last-login', [UserController::class, 'updateLastLogin'])->middleware('permission:users.update');
        Route::put('/{id}/online-status', [UserController::class, 'updateOnlineStatus'])->middleware('permission:users.update');

        // Two-factor authentication
        Route::post('/{id}/two-factor/enable', [UserController::class, 'enableTwoFactor'])->middleware('permission:users.update');
        Route::post('/{id}/two-factor/disable', [UserController::class, 'disableTwoFactor'])->middleware('permission:users.update');

        // Relations
        Route::get('/{id}/wishlists', [UserController::class, 'getUserWishlists'])->middleware('permission:users.view');
        Route::get('/{id}/listings', [UserController::class, 'getUserListings'])->middleware('permission:users.view');
        Route::get('/{id}/bank-cards', [UserController::class, 'getUserBankCards'])->middleware('permission:users.view');
        Route::get('/{id}/auction-history', [UserController::class, 'getUserAuctionHistory'])->middleware('permission:users.view');

        Route::get('/{id}/authentication-logs', [UserController::class, 'getUserAuthenticationLogs'])->middleware('permission:users.view');
    });

    Route::patch('/users/{id}/activate', [UserController::class, 'activateUser']);
    Route::patch('/users/{id}/deactivate', [UserController::class, 'deactivateUser']);

    // ============================================
    // PRICING RULES (ADMIN)
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

    Route::get('admin/promo-codes/usages', [PromoCodeController::class, 'usages'])->middleware('permission:promo-codes.manage');
    Route::apiResource('admin/promo-codes', PromoCodeController::class)->middleware('permission:promo-codes.manage');

    Route::prefix('admin')->group(function () {
        Route::get('/banners', [BannerController::class, 'adminIndex']);
        Route::get('/banners/{id}', [BannerController::class, 'show']);
        Route::post('/banners', [BannerController::class, 'store']);
        Route::put('/banners/{id}', [BannerController::class, 'update']);
        Route::delete('/banners/{id}', [BannerController::class, 'destroy']);
        Route::post('/banners/{id}/toggle', [BannerController::class, 'toggleStatus']);
    });



    // ============================================
    // LISTINGS MANAGEMENT
    // ============================================
    Route::get('/my-ads', [ListingController::class, 'getMyAds']);
    Route::get('/listings', [ListingController::class, 'getAll']);
    Route::get('/listings/draft', [ListingController::class, 'getDraftListings']);
    Route::get('/listings/draft/{id}', [ListingController::class, 'getDraftListingById']);
    Route::delete('/listings/draft/{id}', [ListingController::class, 'deleteDraftListingById']);
    Route::post('/listings', [ListingController::class, 'store']);
    Route::put('/listings/complete/{id}', [ListingController::class, 'completeListing']);
    Route::get('/my-listing', [ListingController::class, 'my_listing']);
    Route::get('/listings/country/{country_id}', [ListingController::class, 'getByCountry']);
    Route::get('/listings/by-city/{city_id}', [ListingController::class, 'getByCity']);
    Route::get('/listings/filter', [ListingController::class, 'filter']);
    Route::get('/listings/recent/city/{city_id}', [ListingController::class, 'getLastByCity']);
    Route::get('/listings/{listingId}/payment-status', [ListingController::class, 'checkListingPaymentStatus']);
    Route::get('/debug-wishlist/{id}', [ListingController::class, 'getDebugInfo']);
    Route::get('/listings/{id}', [ListingController::class, 'getById']);
    Route::get('pricing', [ListingController::class, 'getPriceByModelId']);

    // ============================================
    // LISTING WITH AUCTION
    // ============================================
    Route::post('/listing-with-auction', [ListingAuctionController::class, 'store']);

    // ============================================
    // AUCTIONS
    // ============================================
    Route::prefix('auctions')->group(function () {
        Route::get('/', [ListingAuctionController::class, 'auctionsIndex']);
        Route::post('/', [ListingAuctionController::class, 'auctionsStore']);
        Route::get('/{id}', [ListingAuctionController::class, 'auctionsShow']);
        Route::put('/{id}', [ListingAuctionController::class, 'auctionsUpdate']);
        Route::delete('/{id}', [ListingAuctionController::class, 'auctionsDestroy']);
        Route::get('/my', [ListingAuctionController::class, 'myAuctions']);
    });

    // ============================================
    // LICENSE PLATES (AUTHENTICATED)
    // ============================================
    Route::post('/license-plates', [LicensePlateController::class, 'store']);
    Route::post('/plate-formats', [PlateFormatController::class, 'store']);

    // ============================================
    // WISHLISTS
    // ============================================
    Route::get('/wishlists', [WishlistController::class, 'index']);
    Route::post('/wishlists', [WishlistController::class, 'store']);
    Route::delete('/wishlists/{listing_id}', [WishlistController::class, 'destroy']);

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
    Route::get('/my-garage', [MyGarageController::class, 'index']);
    Route::post('/my-garage', [MyGarageController::class, 'store']);
    Route::get('/my-garage/default', [MyGarageController::class, 'getDefault']);
    Route::get('/my-garage/{id}', [MyGarageController::class, 'show']);
    Route::put('/my-garage/{id}', [MyGarageController::class, 'update']);
    Route::patch('/my-garage/{id}', [MyGarageController::class, 'update']);
    Route::delete('/my-garage/{id}', [MyGarageController::class, 'destroy']);
    Route::post('/my-garage/{id}/set-default', [MyGarageController::class, 'setDefault']);
    Route::get('motorcycle-data', [MyGarageController::class, 'getMotorcycleData']);

    // ============================================
    // SOOM (BIDDING SYSTEM)
    // ============================================
    Route::get('/listings/{listingId}/sooms', [SoomController::class, 'getListingSooms']);
    Route::get('/listings/{listingId}/minimum-soom', [SoomController::class, 'getMinimumSoomAmount']);
    Route::get('/listings/{listingId}/last-soom', [SoomController::class, 'getLastSoom']);
    Route::post('/listings/{listingId}/soom', [SoomController::class, 'createSoom']);
    Route::patch('/listings/{listingId}/mark-as-sold', [SoomController::class, 'markListingAsSold']);
    Route::patch('/listings/{listingId}/close', [SoomController::class, 'closeListing']);
    Route::patch('/listings/{listingId}/reopen', [SoomController::class, 'reopenListing']);

    Route::get('/sooms/max', [SoomController::class, 'getMaxSoom']);
    Route::get('/sooms/max/me', [SoomController::class, 'getMyMaxSoom']);
    Route::get('/sooms/overbidding/users', [SoomController::class, 'getUsersWithOverbidding']);

    Route::patch('/submissions/{submissionId}/accept', [SoomController::class, 'acceptSoom']);
    Route::patch('/submissions/{submissionId}/reject', [SoomController::class, 'rejectSoom']);
    Route::post('/submissions/{submissionId}/validate-sale', [SoomController::class, 'validateSale']);
    Route::put('/submissions/{submissionId}/edit', [SoomController::class, 'editSoom']);
    Route::delete('/submissions/{submissionId}/cancel', [SoomController::class, 'cancelSoom']);

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
    Route::post('/upload-image-no-watermark', [ImageUploadController::class, 'uploadNoWatermark']);
    Route::delete('/delete-image', [ImageUploadController::class, 'delete']);

    // ============================================
    // PAYMENT HISTORY
    // ============================================
    Route::get('/payments/history/user', [PaymentHistoryController::class, 'historyPaymentByUser']);
    Route::get('/payments/history/global', [PaymentHistoryController::class, 'historyPaymentGlobal']);
    Route::get('/payments/{id}', [PaymentHistoryController::class, 'show']);
    Route::get('/payments/stats/user', [PaymentHistoryController::class, 'userStats']);

    // ============================================
    // PAYTABS (AUTHENTICATED)
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
    // GUIDES (AUTHENTICATED)
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

        // Images
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
    // EVENTS (AUTHENTICATED)
    // ============================================
    Route::prefix('event-categories')->group(function () {
        Route::post('/', [EventCategoryController::class, 'store']);
        Route::put('/{id}', [EventCategoryController::class, 'update']);
        Route::delete('/{id}', [EventCategoryController::class, 'destroy']);
    });

    Route::prefix('events')->group(function () {
        Route::post('/', [EventController::class, 'store']);
        Route::put('/{id}', [EventController::class, 'update']);
        Route::delete('/{id}', [EventController::class, 'destroy']);
        Route::get('/{id}/statistics', [EventController::class, 'statistics']);
        Route::post('/{id}/publish', [EventController::class, 'togglePublish']);

        Route::post('/{eventId}/register', [EventParticipantController::class, 'register']);
        Route::delete('/{eventId}/unregister', [EventParticipantController::class, 'unregister']);
        Route::put('/{eventId}/participants/{participantId}/confirm', [EventParticipantController::class, 'confirm']);
        Route::put('/{eventId}/participants/{participantId}/check-in', [EventParticipantController::class, 'checkIn']);
        Route::get('/{eventId}/participants/statistics', [EventParticipantController::class, 'statistics']);
        Route::get('/{eventId}/participants/{participantId}', [EventParticipantController::class, 'show']);
        Route::get('/{eventId}/my-registration', [EventParticipantController::class, 'myRegistration']);

        Route::post('/{eventId}/reviews', [EventReviewController::class, 'store']);
        Route::put('/{eventId}/reviews/{reviewId}', [EventReviewController::class, 'update']);
        Route::delete('/{eventId}/reviews/{reviewId}', [EventReviewController::class, 'destroy']);
        Route::get('/{eventId}/my-review', [EventReviewController::class, 'myReview']);
        Route::get('/{eventId}/reviews/can-review', [EventReviewController::class, 'canReview']);

        Route::post('/{eventId}/favorite', [EventFavoriteController::class, 'store']);
        Route::delete('/{eventId}/unfavorite', [EventFavoriteController::class, 'destroy']);
        Route::get('/{eventId}/is-favorite', [EventFavoriteController::class, 'isFavorite']);
        Route::post('/{eventId}/toggle-favorite', [EventFavoriteController::class, 'toggle']);

        Route::post('/{eventId}/activities', [EventActivityController::class, 'store']);
        Route::put('/{eventId}/activities/{activityId}', [EventActivityController::class, 'update']);
        Route::delete('/{eventId}/activities/{activityId}', [EventActivityController::class, 'destroy']);

        Route::post('/{eventId}/tickets', [EventTicketController::class, 'store']);
        Route::put('/{eventId}/tickets/{ticketId}', [EventTicketController::class, 'update']);
        Route::delete('/{eventId}/tickets/{ticketId}', [EventTicketController::class, 'destroy']);
        Route::post('/{eventId}/tickets/{ticketId}/purchase', [EventTicketController::class, 'purchase']);
        Route::get('/{eventId}/tickets/statistics', [EventTicketController::class, 'statistics']);
        Route::get('/{eventId}/tickets/purchases', [EventTicketController::class, 'eventPurchases']);
        Route::post('/{eventId}/tickets/{ticketId}/toggle-active', [EventTicketController::class, 'toggleActive']);

        Route::post('/{eventId}/contacts', [EventContactController::class, 'store']);
        Route::put('/{eventId}/contacts/{contactId}', [EventContactController::class, 'update']);
        Route::delete('/{eventId}/contacts/{contactId}', [EventContactController::class, 'destroy']);

        Route::post('/{eventId}/faqs', [EventFaqController::class, 'store']);
        Route::put('/{eventId}/faqs/{faqId}', [EventFaqController::class, 'update']);
        Route::delete('/{eventId}/faqs/{faqId}', [EventFaqController::class, 'destroy']);
        Route::post('/{eventId}/faqs/reorder', [EventFaqController::class, 'reorder']);
        Route::delete('/{eventId}/faqs/bulk-delete', [EventFaqController::class, 'bulkDelete']);
        Route::get('/{eventId}/faqs/search', [EventFaqController::class, 'search']);

        Route::post('/{eventId}/updates', [EventUpdateController::class, 'store']);
        Route::put('/{eventId}/updates/{updateId}', [EventUpdateController::class, 'update']);
        Route::delete('/{eventId}/updates/{updateId}', [EventUpdateController::class, 'destroy']);
        Route::delete('/{eventId}/updates/bulk-delete', [EventUpdateController::class, 'bulkDelete']);

        Route::get('/{eventId}/sponsors', [EventSponsorController::class, 'eventSponsors']);
        Route::post('/{eventId}/sponsors/{sponsorId}/attach', [EventSponsorController::class, 'attachToEvent']);
        Route::delete('/{eventId}/sponsors/{sponsorId}/detach', [EventSponsorController::class, 'detachFromEvent']);
        Route::put('/{eventId}/sponsors/{sponsorId}/update-level', [EventSponsorController::class, 'updateSponsorLevel']);
    });

    Route::prefix('tickets')->group(function () {
        Route::get('/{purchaseId}', [EventTicketController::class, 'showPurchase']);
        Route::post('/{purchaseId}/check-in', [EventTicketController::class, 'checkIn']);
        Route::get('/verify/{qrCode}', [EventTicketController::class, 'verifyQRCode']);
    });

    Route::get('/my-events', [EventParticipantController::class, 'myEvents']);
    Route::get('/my-favorite-events', [EventFavoriteController::class, 'myFavorites']);
    Route::delete('/my-favorite-events/clear', [EventFavoriteController::class, 'clearAll']);
    Route::get('/my-favorite-events/count', [EventFavoriteController::class, 'count']);
    Route::get('/my-tickets', [EventTicketController::class, 'myTickets']);
    Route::get('/my-organized-events', [EventController::class, 'myOrganizedEvents']);
    Route::get('/my-reviews', [EventReviewController::class, 'myReviews']);
    Route::get('/my-event-updates', [EventUpdateController::class, 'myEventUpdates']);

    Route::prefix('event-sponsors')->group(function () {
        Route::get('/', [EventSponsorController::class, 'index']);
        Route::post('/', [EventSponsorController::class, 'store']);
        Route::get('/{id}', [EventSponsorController::class, 'show']);
        Route::put('/{id}', [EventSponsorController::class, 'update']);
        Route::delete('/{id}', [EventSponsorController::class, 'destroy']);
    });

    // ============================================
    // NEWSLETTER (AUTHENTICATED)
    // ============================================
    Route::get('/newsletter/preferences', [NewsletterController::class, 'getPreferences']);
    Route::put('/newsletter/preferences', [NewsletterController::class, 'updatePreferences']);

    Route::prefix('newsletter')->group(function () {
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
    // POI (AUTHENTICATED)
    // ============================================
    Route::post('/poi-types', [PoiTypeController::class, 'store']);
    Route::put('/poi-types/{id}', [PoiTypeController::class, 'update']);
    Route::patch('/poi-types/{id}', [PoiTypeController::class, 'update']);
    Route::delete('/poi-types/{id}', [PoiTypeController::class, 'destroy']);

    Route::post('/poi-services', [PoiServiceController::class, 'store']);
    Route::put('/poi-services/{id}', [PoiServiceController::class, 'update']);
    Route::delete('/poi-services/{id}', [PoiServiceController::class, 'destroy']);

    Route::get('/pois', [PointOfInterestController::class, 'index']);
    Route::post('/pois', [PointOfInterestController::class, 'store']);
    Route::get('/pois/{id}', [PointOfInterestController::class, 'show']);
    Route::put('/pois/{id}', [PointOfInterestController::class, 'update']);
    Route::delete('/pois/{id}', [PointOfInterestController::class, 'destroy']);
    Route::post('/pois/{id}/favorite', [PointOfInterestController::class, 'toggleFavorite']);
    Route::get('/pois/nearby', [PointOfInterestController::class, 'nearby']);
    Route::get('/pois/favorites', [PointOfInterestController::class, 'favorites']);

    Route::get('/pois/{poi_id}/reviews', [PoiReviewController::class, 'index']);
    Route::post('/pois/{poi_id}/reviews', [PoiReviewController::class, 'store']);
    Route::put('/pois/{poi_id}/reviews/{id}', [PoiReviewController::class, 'update']);
    Route::delete('/pois/{poi_id}/reviews/{id}', [PoiReviewController::class, 'destroy']);

    Route::get('/pois/{poi_id}/reports', [PoiReportController::class, 'index']);
    Route::post('/pois/{poi_id}/reports', [PoiReportController::class, 'store']);
    Route::get('/pois/{poi_id}/reports/{id}', [PoiReportController::class, 'show']);
    Route::put('/pois/{poi_id}/reports/{id}/status', [PoiReportController::class, 'updateStatus']);
    Route::delete('/pois/{poi_id}/reports/{id}', [PoiReportController::class, 'destroy']);
    Route::get('/reports/pending', [PoiReportController::class, 'pending']);
    Route::get('/reports/stats', [PoiReportController::class, 'stats']);
    Route::get('/user/reports', [PoiReportController::class, 'userReports']);

    // ============================================
    // ROUTES (AUTHENTICATED)
    // ============================================
    Route::post('/route-categories', [RouteCategoryController::class, 'store']);
    Route::put('/route-categories/{id}', [RouteCategoryController::class, 'update']);
    Route::delete('/route-categories/{id}', [RouteCategoryController::class, 'destroy']);

    Route::post('/route-tags', [RouteTagController::class, 'store']);
    Route::put('/route-tags/{id}', [RouteTagController::class, 'update']);
    Route::delete('/route-tags/{id}', [RouteTagController::class, 'destroy']);

    Route::get('/routes', [RouteController::class, 'index']);
    Route::post('/routes', [RouteController::class, 'store']);
    Route::get('/routes/{id}', [RouteController::class, 'show']);
    Route::put('/routes/{id}', [RouteController::class, 'update']);
    Route::delete('/routes/{id}', [RouteController::class, 'destroy']);
    Route::post('/routes/{id}/like', [RouteController::class, 'toggleLike']);
    Route::post('/routes/{id}/favorite', [RouteController::class, 'toggleFavorite']);

    Route::get('/routes/{route_id}/waypoints', [RouteWaypointController::class, 'index']);
    Route::post('/routes/{route_id}/waypoints', [RouteWaypointController::class, 'store']);
    Route::get('/routes/{route_id}/waypoints/{id}', [RouteWaypointController::class, 'show']);
    Route::put('/routes/{route_id}/waypoints/{id}', [RouteWaypointController::class, 'update']);
    Route::delete('/routes/{route_id}/waypoints/{id}', [RouteWaypointController::class, 'destroy']);
    Route::put('/routes/{route_id}/waypoints/{id}/reorder', [RouteWaypointController::class, 'reorder']);

    Route::get('/routes/{route_id}/reviews', [RouteReviewController::class, 'index']);
    Route::post('/routes/{route_id}/reviews', [RouteReviewController::class, 'store']);
    Route::delete('/routes/{route_id}/reviews/{id}', [RouteReviewController::class, 'destroy']);

    Route::get('/routes/{route_id}/warnings', [RouteWarningController::class, 'index']);
    Route::post('/routes/{route_id}/warnings', [RouteWarningController::class, 'store']);
    Route::get('/routes/{route_id}/warnings/{id}', [RouteWarningController::class, 'show']);
    Route::put('/routes/{route_id}/warnings/{id}', [RouteWarningController::class, 'update']);
    Route::delete('/routes/{route_id}/warnings/{id}', [RouteWarningController::class, 'destroy']);
    Route::put('/routes/{route_id}/warnings/{id}/deactivate', [RouteWarningController::class, 'deactivate']);
    Route::get('/warnings/active', [RouteWarningController::class, 'getAllActive']);

    Route::get('/routes/{route_id}/completions', [RouteCompletionController::class, 'index']);
    Route::post('/routes/{route_id}/completions', [RouteCompletionController::class, 'store']);
    Route::get('/routes/{route_id}/completions/{id}', [RouteCompletionController::class, 'show']);
    Route::put('/routes/{route_id}/completions/{id}', [RouteCompletionController::class, 'update']);
    Route::delete('/routes/{route_id}/completions/{id}', [RouteCompletionController::class, 'destroy']);
    Route::get('/routes/{route_id}/check-completion', [RouteCompletionController::class, 'checkCompletion']);
    Route::get('/user/completions', [RouteCompletionController::class, 'userCompletions']);
    Route::get('/user/completion-stats', [RouteCompletionController::class, 'userStats']);

    // ============================================
    // PERMISSIONS & ROLES (ADMIN)
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
            Route::get('/grouped', [RolePermissionController::class, 'getGroupedPermissions'])->middleware('permission:roles.view');
            Route::post('/', [RolePermissionController::class, 'store'])->middleware('permission:roles.update');
            Route::post('/sync', [RolePermissionController::class, 'sync'])->middleware('permission:roles.update');
            Route::delete('/{permissionId}', [RolePermissionController::class, 'destroy'])->middleware('permission:roles.update');
            Route::get('/interface/{interface}', [RolePermissionController::class, 'getPermissionsByInterface'])->middleware('permission:roles.view');
        });
        Route::get('/menus', [AdminMenuController::class, 'index']);

        // Admin-only menu management routes
        Route::middleware(['permission:manage_menus'])->prefix('menus')->group(function () {
            Route::get('/all', [AdminMenuController::class, 'all']);
            Route::post('/', [AdminMenuController::class, 'store']);
            Route::get('/{id}', [AdminMenuController::class, 'show']);
            Route::put('/{id}', [AdminMenuController::class, 'update']);
            Route::delete('/{id}', [AdminMenuController::class, 'destroy']);
            Route::post('/reorder', [AdminMenuController::class, 'reorder']);
        });
    });
});

// ============================================
// ADMIN ROUTES WITH SPECIFIC MIDDLEWARE
// ============================================
Route::middleware(['auth:api', 'admin'])->prefix('admin')->group(function () {
    Route::post('/listings/republish-paid', [ListingController::class, 'checkAndRepublishPaidListings']);
    Route::get('/listings/payment-stats', [ListingController::class, 'getListingPaymentStats']);
    Route::post('/payments/{paymentId}/force-verify', [ListingController::class, 'forcePaymentVerification']);
});

Route::middleware('auth:api')->group(function () {

    // ========================================
    // ENVOI DE NOTIFICATIONS EMAIL
    // ========================================
    Route::prefix('notifications')->group(function () {
        // Envoyer des notifications
        Route::post('/send', [EmailNotificationController::class, 'send']);
        Route::post('/send-custom', [EmailNotificationController::class, 'sendCustom']);
        Route::post('/send-multiple', [EmailNotificationController::class, 'sendMultiple']);
        Route::post('/broadcast', [EmailNotificationController::class, 'broadcast']);
        Route::post('/test-email', [EmailNotificationController::class, 'testEmail']);

        // Gestion des notifications (lecture, suppression)
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/{id}', [NotificationController::class, 'show']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/', [NotificationController::class, 'destroyAll']);
    });

    // ========================================
    // PRÉFÉRENCES DE NOTIFICATIONS
    // ========================================
    Route::prefix('notification-preferences')->group(function () {
        Route::get('/', [ControllerssNotificationPreferenceController::class, 'show']);
        Route::put('/', [ControllerssNotificationPreferenceController::class, 'update']);
        Route::post('/enable-all', [ControllerssNotificationPreferenceController::class, 'enableAll']);
        Route::post('/disable-all', [ControllerssNotificationPreferenceController::class, 'disableAll']);
    });
});
