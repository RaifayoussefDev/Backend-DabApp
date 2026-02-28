<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Mail\UserCreatedMail;
use App\Models\Authentication;
use App\Models\Payment;
use App\Models\Submission;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\PointOfInterest;
use App\Models\Role;
use App\Models\Permission;

class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/users",
     *     operationId="getUsersList",
     *     tags={"Users Management"},
     *     summary="Get paginated list of users with filters",
     *     description="Returns paginated list of users. Supports search, filtering by role, active status, verification status, country, and custom sorting.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15, default=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by first name, last name, email, or phone",
     *         required=false,
     *         @OA\Schema(type="string", example="john")
     *     ),
     *     @OA\Parameter(
     *         name="role_id",
     *         in="query",
     *         description="Filter by role ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="verified",
     *         in="query",
     *         description="Filter by verification status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Filter by country ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string", example="created_at", default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc", default="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved users list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="first_name", type="string", example="John"),
     *                         @OA\Property(property="last_name", type="string", example="Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com"),
     *                         @OA\Property(property="phone", type="string", example="+212612345678"),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="verified", type="boolean", example=true),
     *                         @OA\Property(property="role_id", type="integer", example=2),
     *                         @OA\Property(property="created_at", type="string", example="2024-01-15T10:30:00Z")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Missing permission")
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $roleId = $request->get('role_id');
        $isActive = $request->get('is_active');
        $verified = $request->get('verified');
        $countryId = $request->get('country_id');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        // ⭐ NOUVEAUX FILTRES
        $isOnline = $request->get('is_online');
        $twoFactorEnabled = $request->get('two_factor_enabled');
        $gender = $request->get('gender');
        $language = $request->get('language');
        $createdFrom = $request->get('created_from');
        $createdTo = $request->get('created_to');
        $lastLoginFrom = $request->get('last_login_from');
        $lastLoginTo = $request->get('last_login_to');

        $query = User::with(['role', 'country']);

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        // Role filter
        if ($roleId) {
            $query->where('role_id', $roleId);
        }

        // ⭐ FIX: is_active filter (gère string et boolean)
        if ($isActive !== null && $isActive !== '') {
            // Convertir "true"/"false" string en boolean
            $isActiveBool = filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActiveBool !== null) {
                $query->where('is_active', $isActiveBool);
            }
        }

        // ⭐ FIX: verified filter (gère string et boolean)
        if ($verified !== null && $verified !== '') {
            // Convertir "true"/"false" string en boolean
            $verifiedBool = filter_var($verified, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($verifiedBool !== null) {
                $query->where('verified', $verifiedBool);
            }
        }

        // Country filter
        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        // ⭐ NOUVEAU: is_online filter
        if ($isOnline !== null && $isOnline !== '') {
            $isOnlineBool = filter_var($isOnline, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isOnlineBool !== null) {
                $query->where('is_online', $isOnlineBool);
            }
        }

        // ⭐ NOUVEAU: two_factor_enabled filter
        if ($twoFactorEnabled !== null && $twoFactorEnabled !== '') {
            $twoFactorBool = filter_var($twoFactorEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($twoFactorBool !== null) {
                $query->where('two_factor_enabled', $twoFactorBool);
            }
        }

        // ⭐ NOUVEAU: Gender filter
        if ($gender) {
            $query->where('gender', $gender);
        }

        // ⭐ NOUVEAU: Language filter
        if ($language) {
            $query->where('language', $language);
        }

        // ⭐ NOUVEAU: Created date range filter
        if ($createdFrom) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }
        if ($createdTo) {
            $query->whereDate('created_at', '<=', $createdTo);
        }

        // ⭐ NOUVEAU: Last login date range filter
        if ($lastLoginFrom) {
            $query->whereDate('last_login', '>=', $lastLoginFrom);
        }
        if ($lastLoginTo) {
            $query->whereDate('last_login', '<=', $lastLoginTo);
        }

        // Sorting
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users,
            // ⭐ DEBUG (à retirer en production)
            'debug' => [
                'filters_applied' => [
                    'search' => $search,
                    'role_id' => $roleId,
                    'is_active' => $isActive,
                    'verified' => $verified,
                    'is_online' => $isOnline,
                    'two_factor_enabled' => $twoFactorEnabled,
                    'gender' => $gender,
                    'language' => $language,
                    'country_id' => $countryId,
                    'created_from' => $createdFrom,
                    'created_to' => $createdTo,
                    'last_login_from' => $lastLoginFrom,
                    'last_login_to' => $lastLoginTo,
                ],
                'total_results' => $users->total()
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/users",
     *     operationId="createUser",
     *     tags={"Users Management"},
     *     summary="Create a new user",
     *     description="Creates a new user account. Auto-generates a password and sends it via email to the user.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="User data",
     *         @OA\JsonContent(
     *             required={"first_name", "last_name", "email", "role_id"},
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="birthday", type="string", format="date", example="1990-01-15"),
     *             @OA\Property(property="gender", type="string", enum={"male", "female", "other"}, example="male"),
     *             @OA\Property(property="profile_picture", type="string", example="profiles/user123.jpg"),
     *             @OA\Property(property="address", type="string", example="123 Main St, City"),
     *             @OA\Property(property="postal_code", type="string", example="12345"),
     *             @OA\Property(property="role_id", type="integer", example=2),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="language", type="string", example="en"),
     *             @OA\Property(property="timezone", type="string", example="America/New_York")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john.doe@example.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Missing permission")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|unique:users,phone',
            'birthday' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other',
            'profile_picture' => 'nullable|string',
            'address' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'role_id' => 'required|exists:roles,id',
            'country_id' => 'nullable|exists:countries,id',
            'language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
        ]);

        $generatedPassword = Str::random(12);
        $validated['password'] = Hash::make($generatedPassword);
        $validated['is_active'] = true;
        $validated['verified'] = false; // Blue Tick defaults to false
        $validated['is_registration_completed'] = true; // Admin created users don't need OTP

        $user = User::create($validated);

        try {
            Mail::to($user->email)->send(new UserCreatedMail($user, $generatedPassword));
        } catch (\Exception $e) {
            \Log::error('Failed to send user creation email: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user->load('role', 'country')
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/{id}",
     *     operationId="getUserById",
     *     tags={"Users Management"},
     *     summary="Get complete user details by ID",
     *     description="Returns detailed user information including role, country, bank cards, wishlists, listings, payments, sooms sent/received, and comprehensive statistics.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="with_trashed",
     *         in="query",
     *         description="Include soft-deleted users",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved user details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     description="Complete user information with relations"
     *                 ),
     *                 @OA\Property(
     *                     property="stats",
     *                     type="object",
     *                     @OA\Property(property="total_listings", type="integer", example=5),
     *                     @OA\Property(property="active_listings", type="integer", example=3),
     *                     @OA\Property(property="published_listings", type="integer", example=2),
     *                     @OA\Property(property="draft_listings", type="integer", example=1),
     *                     @OA\Property(property="sold_listings", type="integer", example=1),
     *                     @OA\Property(property="total_wishlists", type="integer", example=10),
     *                     @OA\Property(property="total_bank_cards", type="integer", example=2),
     *                     @OA\Property(property="auction_participations", type="integer", example=15),
     *                     @OA\Property(property="auction_wins", type="integer", example=5),
     *                     @OA\Property(property="total_sooms_sent", type="integer", example=8),
     *                     @OA\Property(property="pending_sooms", type="integer", example=2),
     *                     @OA\Property(property="accepted_sooms", type="integer", example=4),
     *                     @OA\Property(property="rejected_sooms", type="integer", example=2),
     *                     @OA\Property(property="validated_sales", type="integer", example=3),
     *                     @OA\Property(property="total_sooms_received", type="integer", example=12),
     *                     @OA\Property(property="pending_sooms_received", type="integer", example=3),
     *                     @OA\Property(property="accepted_sooms_received", type="integer", example=5),
     *                     @OA\Property(property="pending_validation", type="integer", example=2),
     *                     @OA\Property(property="total_payments", type="integer", example=4),
     *                     @OA\Property(property="completed_payments", type="integer", example=3),
     *                     @OA\Property(property="pending_payments", type="integer", example=1),
     *                     @OA\Property(property="failed_payments", type="integer", example=0),
     *                     @OA\Property(property="total_payment_amount", type="number", example=250.50)
     *                 ),
     *                 @OA\Property(
     *                     property="sooms_sent",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=8),
     *                     @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(
     *                     property="sooms_received",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=12),
     *                     @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(
     *                     property="payments",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=4),
     *                     @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(property="is_deleted", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Missing permission")
     * )
     */
    public function show(Request $request, string $id)
    {
        try {
            $withTrashed = filter_var($request->get('with_trashed', false), FILTER_VALIDATE_BOOLEAN);

            $query = User::query();

            if ($withTrashed) {
                $query->withTrashed();
            }

            // ⭐ Charger TOUTES les relations avec les BONS noms de colonnes
            $user = $query->with([
                // Relations de base
                'role',
                'country',

                // Bank Cards
                'bankCards.cardType',

                // Wishlists
                'wishlists.listing',

                // ⭐ Listings avec toutes leurs relations
                'listings' => function ($query) {
                    $query->with([
                        'category',
                        'country',
                        'city',
                        'images', // ← Laravel va automatiquement charger toutes les colonnes
                        'motorcycle',
                        'sparePart',
                        'licensePlate',
                        'listingType'
                    ])->withCount([
                                'images',
                                'auctionHistories',
                                'payments'
                            ]);
                }
            ])->findOrFail($id);

            // ⭐ Charger les SOOMs (Submissions) envoyés par l'utilisateur
            $userSubmissions = Submission::with([
                'listing' => function ($query) {
                    $query->with([
                        'seller',
                        'images' // ← Pas besoin de spécifier les colonnes
                    ]);
                }
            ])
                ->where('user_id', $id)
                ->orderBy('submission_date', 'desc')
                ->get();

            // ⭐ Charger les SOOMs reçus sur les listings de l'utilisateur
            $receivedSubmissions = Submission::with([
                'user',
                'listing'
            ])
                ->whereHas('listing', function ($query) use ($id) {
                    $query->where('seller_id', $id);
                })
                ->orderBy('submission_date', 'desc')
                ->get();

            // ⭐ Charger les paiements de l'utilisateur
            $userPayments = \App\Models\Payment::with([
                'listing.category'
            ])
                ->whereHas('listing', function ($query) use ($id) {
                    $query->where('seller_id', $id);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            // ⭐ Statistiques complètes
            $userStats = [
                // Listings stats
                'total_listings' => $user->listings->count(),
                'active_listings' => $user->listings->where('status', 'active')->count(),
                'published_listings' => $user->listings->where('status', 'published')->count(),
                'draft_listings' => $user->listings->where('status', 'draft')->count(),
                'sold_listings' => $user->listings->where('status', 'sold')->count(),

                // Wishlist stats
                'total_wishlists' => $user->wishlists->count(),

                // Bank cards stats
                'total_bank_cards' => $user->bankCards->count(),

                // Auction stats
                'auction_participations' => DB::table('auction_histories')
                    ->where('buyer_id', $id)
                    ->count(),
                'auction_wins' => DB::table('auction_histories')
                    ->where('buyer_id', $id)
                    ->where('validated', 1)
                    ->count(),

                // ⭐ SOOM stats (comme acheteur)
                'total_sooms_sent' => $userSubmissions->count(),
                'pending_sooms' => $userSubmissions->where('status', 'pending')->count(),
                'accepted_sooms' => $userSubmissions->where('status', 'accepted')->count(),
                'rejected_sooms' => $userSubmissions->where('status', 'rejected')->count(),
                'validated_sales' => $userSubmissions->where('sale_validated', true)->count(),

                // ⭐ SOOM stats (comme vendeur)
                'total_sooms_received' => $receivedSubmissions->count(),
                'pending_sooms_received' => $receivedSubmissions->where('status', 'pending')->count(),
                'accepted_sooms_received' => $receivedSubmissions->where('status', 'accepted')->count(),
                'pending_validation' => $receivedSubmissions->where('status', 'accepted')
                    ->where('sale_validated', false)
                    ->count(),

                // ⭐ Payment stats
                'total_payments' => $userPayments->count(),
                'completed_payments' => $userPayments->where('payment_status', 'completed')->count(),
                'pending_payments' => $userPayments->where('payment_status', 'pending')->count(),
                'failed_payments' => $userPayments->where('payment_status', 'failed')->count(),
                'total_payment_amount' => $userPayments->where('payment_status', 'completed')
                    ->sum('amount'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'stats' => $userStats,

                    // ⭐ SOOMs envoyés par l'utilisateur (comme acheteur)
                    'sooms_sent' => [
                        'total' => $userSubmissions->count(),
                        'data' => $userSubmissions
                    ],

                    // ⭐ SOOMs reçus par l'utilisateur (comme vendeur)
                    'sooms_received' => [
                        'total' => $receivedSubmissions->count(),
                        'data' => $receivedSubmissions
                    ],

                    // ⭐ Paiements de l'utilisateur
                    'payments' => [
                        'total' => $userPayments->count(),
                        'data' => $userPayments
                    ],

                    'is_deleted' => $user->trashed()
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "User with ID {$id} not found"
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error in UserController@show', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin/users/{id}",
     *     operationId="updateUser",
     *     tags={"Users Management"},
     *     summary="Update user details",
     *     description="Updates user information. All fields are optional. Only provided fields will be updated.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.new@example.com"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="birthday", type="string", format="date", example="1990-01-15"),
     *             @OA\Property(property="gender", type="string", enum={"male", "female", "other"}, example="male"),
     *             @OA\Property(property="profile_picture", type="string", example="profiles/user123.jpg"),
     *             @OA\Property(property="address", type="string", example="456 New St"),
     *             @OA\Property(property="postal_code", type="string", example="54321"),
     *             @OA\Property(property="role_id", type="integer", example=3),
     *             @OA\Property(property="country_id", type="integer", example=2),
     *             @OA\Property(property="language", type="string", example="fr"),
     *             @OA\Property(property="timezone", type="string", example="Europe/Paris"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Missing permission")
     * )
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|unique:users,phone,' . $id,
            'birthday' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other',
            'profile_picture' => 'nullable|string',
            'address' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'role_id' => 'sometimes|required|exists:roles,id',
            'country_id' => 'nullable|exists:countries,id',
            'language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
        ]);

        $user->update($validated);



        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->load('role', 'country')
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/users/{id}",
     *     operationId="deleteUser",
     *     tags={"Users Management"},
     *     summary="Soft delete user",
     *     description="Soft deletes a user (can be restored later). User data is retained but marked as deleted.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Missing permission")
     * )
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/users/{id}/force-delete",
     *     operationId="forceDeleteUser",
     *     tags={"Users Management"},
     *     summary="Permanently delete user",
     *     description="Permanently deletes a user from the database. This action cannot be undone.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User permanently deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User permanently deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function forceDelete(string $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'User permanently deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/users/{id}/restore",
     *     operationId="restoreUser",
     *     tags={"Users Management"},
     *     summary="Restore soft-deleted user",
     *     description="Restores a previously soft-deleted user account.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User restored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User restored successfully")
     *         )
     *     )
     * )
     */
    public function restore(string $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully',
            'data' => $user
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/users/{id}/activate",
     *     operationId="activateUser",
     *     tags={"Users Management"},
     *     summary="Activate user account",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User activated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User activated successfully")
     *         )
     *     )
     * )
     */
    public function activate(string $id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'User activated successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/users/{id}/deactivate",
     *     operationId="deactivateUser",
     *     tags={"Users Management"},
     *     summary="Deactivate user account",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User deactivated successfully")
     *         )
     *     )
     * )
     */
    public function deactivate(string $id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'User deactivated successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/users/{id}/verify",
     *     operationId="verifyUser",
     *     tags={"Users Management"},
     *     summary="Verify user account",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User verified successfully")
     *         )
     *     )
     * )
     */
    public function verifyUser(string $id)
    {
        $user = User::findOrFail($id);
        $user->update(['verified' => true]);

        return response()->json([
            'success' => true,
            'message' => 'User verified successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/users/{id}/reset-password",
     *     operationId="resetUserPassword",
     *     tags={"Users Management"},
     *     summary="Reset user password",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password reset successfully and email sent")
     *         )
     *     )
     * )
     */
    public function resetPassword(string $id)
    {
        $user = User::findOrFail($id);

        $newPassword = Str::random(12);
        $user->update(['password' => Hash::make($newPassword)]);

        try {
            Mail::to($user->email)->send(new UserCreatedMail($user, $newPassword));
        } catch (\Exception $e) {
            \Log::error('Failed to send password reset email: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully and email sent'
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/users/{id}/change-password",
     *     operationId="changeUserPassword",
     *     tags={"Users Management"},
     *     summary="Change user password",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"new_password", "new_password_confirmation"},
     *             @OA\Property(property="new_password", type="string", format="password", example="NewPassword123!"),
     *             @OA\Property(property="new_password_confirmation", type="string", format="password", example="NewPassword123!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password changed successfully")
     *         )
     *     )
     * )
     */
    public function changePassword(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'new_password' => 'required|string|min:8|confirmed'
        ]);

        $user->update(['password' => Hash::make($validated['new_password'])]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/users/{id}/profile-picture",
     *     operationId="updateUserProfilePicture",
     *     tags={"Users Management"},
     *     summary="Update user profile picture",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"profile_picture"},
     *                 @OA\Property(
     *                     property="profile_picture",
     *                     type="string",
     *                     format="binary"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile picture updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile picture updated successfully")
     *         )
     *     )
     * )
     */
    public function updateProfilePicture(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $path = $request->file('profile_picture')->store('profile_pictures', 'public');
            $user->update(['profile_picture' => $path]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully',
            'data' => ['profile_picture' => $user->profile_picture]
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/users/{id}/last-login",
     *     operationId="updateUserLastLogin",
     *     tags={"Users Management"},
     *     summary="Update user last login timestamp",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Last login updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Last login updated successfully")
     *         )
     *     )
     * )
     */
    public function updateLastLogin(string $id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'last_login' => Carbon::now(),
            'is_online' => true
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Last login updated successfully'
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/users/{id}/online-status",
     *     operationId="updateUserOnlineStatus",
     *     tags={"Users Management"},
     *     summary="Update user online status",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_online"},
     *             @OA\Property(property="is_online", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Online status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Online status updated successfully")
     *         )
     *     )
     * )
     */
    public function updateOnlineStatus(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'is_online' => 'required|boolean'
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Online status updated successfully',
            'data' => ['is_online' => $user->is_online]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/users/{id}/two-factor/enable",
     *     operationId="enableUserTwoFactor",
     *     tags={"Users Management"},
     *     summary="Enable two-factor authentication",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="2FA enabled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Two-factor authentication enabled successfully")
     *         )
     *     )
     * )
     */
    public function enableTwoFactor(string $id)
    {
        $user = User::findOrFail($id);
        $user->update(['two_factor_enabled' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication enabled successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/users/{id}/two-factor/disable",
     *     operationId="disableUserTwoFactor",
     *     tags={"Users Management"},
     *     summary="Disable two-factor authentication",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="2FA disabled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Two-factor authentication disabled successfully")
     *         )
     *     )
     * )
     */
    public function disableTwoFactor(string $id)
    {
        $user = User::findOrFail($id);
        $user->update(['two_factor_enabled' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication disabled successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/{id}/wishlists",
     *     operationId="getUserWishlists",
     *     tags={"Users Management"},
     *     summary="Get user's wishlist items",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved wishlists",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getUserWishlists(string $id)
    {
        $user = User::findOrFail($id);
        $wishlists = $user->wishlists()->with('listing')->get();

        return response()->json([
            'success' => true,
            'data' => $wishlists
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/{id}/listings",
     *     operationId="getUserListings",
     *     tags={"Users Management"},
     *     summary="Get user's listings with complete details",
     *     description="Returns all listings of a specific user with complete details including images, category-specific data, and statistics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by listing status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"draft", "published", "active", "sold", "closed"}, example="published")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved listings",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Honda CBR 600RR"),
     *                     @OA\Property(property="description", type="string", example="Excellent condition"),
     *                     @OA\Property(property="price", type="number", example=50000),
     *                     @OA\Property(property="display_price", type="number", example=50000),
     *                     @OA\Property(property="status", type="string", example="published"),
     *                     @OA\Property(property="category_id", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", example="2024-12-02 10:30:00"),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="city", type="string", example="Casablanca"),
     *                     @OA\Property(property="country", type="string", example="Morocco"),
     *                     @OA\Property(property="motorcycle", type="object", nullable=true),
     *                     @OA\Property(property="spare_part", type="object", nullable=true),
     *                     @OA\Property(property="license_plate", type="object", nullable=true),
     *                     @OA\Property(property="stats", type="object")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="summary",
     *                 type="object",
     *                 @OA\Property(property="total_listings", type="integer", example=10),
     *                 @OA\Property(property="published", type="integer", example=5),
     *                 @OA\Property(property="draft", type="integer", example=2),
     *                 @OA\Property(property="sold", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function getUserListings(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);

            // Filtres
            $status = $request->get('status');
            $categoryId = $request->get('category_id');

            $query = $user->listings()->with([
                'images',
                'city',
                'country',
                'country.currencyExchangeRate',
                'category',
                'listingType',
                'motorcycle.brand',
                'motorcycle.model',
                'motorcycle.year',
                'sparePart.bikePartBrand',
                'sparePart.bikePartCategory',
                'sparePart.motorcycleAssociations.brand',
                'sparePart.motorcycleAssociations.model',
                'sparePart.motorcycleAssociations.year',
                'licensePlate.format',
                'licensePlate.city',
                'licensePlate.fieldValues.formatField'
            ]);

            // Appliquer les filtres
            if ($status) {
                $query->where('status', $status);
            }

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            $listings = $query->orderBy('created_at', 'desc')->get();

            // Formater chaque listing
            $formattedListings = $listings->map(function ($listing) {
                // Récupérer current_bid pour les enchères
                $currentBid = null;
                if ($listing->auction_enabled) {
                    $currentBid = DB::table('auction_histories')
                        ->where('listing_id', $listing->id)
                        ->max('bid_amount');
                }

                // Déterminer le prix à afficher
                $displayPrice = $listing->price;
                $isAuction = false;

                if (!$displayPrice && $listing->auction_enabled) {
                    $displayPrice = $currentBid ?: $listing->minimum_bid;
                    $isAuction = true;
                }

                // Récupérer le symbole de devise
                $currencySymbol = $listing->country?->currencyExchangeRate?->currency_symbol ?? 'MAD';

                $data = [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $listing->updated_at->format('Y-m-d H:i:s'),
                    'city' => $listing->city?->name,
                    'country' => $listing->country?->name,
                    'images' => $listing->images->pluck('image_url'),
                    'category_id' => $listing->category_id,
                    'category_name' => $listing->category?->name,
                    'allow_submission' => $listing->allow_submission,
                    'auction_enabled' => $listing->auction_enabled,
                    'minimum_bid' => $listing->minimum_bid,
                    'listing_type_id' => $listing->listing_type_id,
                    'listing_type_name' => $listing->listingType?->name,
                    'contacting_channel' => $listing->contacting_channel,
                    'seller_type' => $listing->seller_type,
                    'status' => $listing->status,
                    'currency' => $currencySymbol,
                    'display_price' => $displayPrice,
                    'is_auction' => $isAuction,
                    'current_bid' => $currentBid,
                ];

                if (!$listing->allow_submission) {
                    $data['price'] = $listing->price ?? $listing->minimum_bid;
                }

                // Stats du listing
                $data['stats'] = [
                    'views' => 0, // À implémenter si vous avez un système de vues
                    'wishlists' => DB::table('wishlists')->where('listing_id', $listing->id)->count(),
                    'submissions' => DB::table('submissions')->where('listing_id', $listing->id)->count(),
                    'images_count' => $listing->images->count(),
                    'payments_count' => $listing->payments()->count(),
                    'completed_payments' => $listing->payments()->where('payment_status', 'completed')->count(),
                ];

                // Motorcycle category
                if ($listing->category_id == 1 && $listing->motorcycle) {
                    $data['motorcycle'] = [
                        'brand' => $listing->motorcycle->brand?->name,
                        'model' => $listing->motorcycle->model?->name,
                        'year' => $listing->motorcycle->year?->year,
                        'engine' => $listing->motorcycle->engine,
                        'mileage' => $listing->motorcycle->mileage,
                        'body_condition' => $listing->motorcycle->body_condition,
                        'modified' => $listing->motorcycle->modified,
                        'insurance' => $listing->motorcycle->insurance,
                        'general_condition' => $listing->motorcycle->general_condition,
                        'vehicle_care' => $listing->motorcycle->vehicle_care,
                        'transmission' => $listing->motorcycle->transmission,
                    ];
                }

                // Spare part category
                if ($listing->category_id == 2 && $listing->sparePart) {
                    $data['spare_part'] = [
                        'condition' => $listing->sparePart->condition,
                        'brand' => $listing->sparePart->bikePartBrand?->name,
                        'category' => $listing->sparePart->bikePartCategory?->name,
                        'compatible_motorcycles' => $listing->sparePart->motorcycleAssociations->map(function ($association) {
                            return [
                                'brand' => $association->brand?->name,
                                'model' => $association->model?->name,
                                'year' => $association->year?->year,
                            ];
                        }),
                    ];
                }

                // License plate category
                if ($listing->category_id == 3 && $listing->licensePlate) {
                    $licensePlate = $listing->licensePlate;

                    $data['license_plate'] = [
                        'plate_format' => [
                            'id' => $licensePlate->format?->id,
                            'name' => $licensePlate->format?->name,
                            'pattern' => $licensePlate->format?->pattern ?? null,
                            'country' => $licensePlate->format?->country ?? null,
                        ],
                        'city' => $licensePlate->city?->name,
                        'country_id' => $licensePlate->country_id,
                        'fields' => $licensePlate->fieldValues->map(function ($fieldValue) {
                            return [
                                'field_id' => $fieldValue->formatField?->id,
                                'field_name' => $fieldValue->formatField?->field_name,
                                'position' => $fieldValue->formatField?->position,
                                'character_type' => $fieldValue->formatField?->character_type,
                                'is_required' => $fieldValue->formatField?->is_required,
                                'min_length' => $fieldValue->formatField?->min_length,
                                'max_length' => $fieldValue->formatField?->max_length,
                                'value' => $fieldValue->field_value,
                            ];
                        })->toArray(),
                    ];
                }

                return $data;
            });

            // Résumé des listings
            $summary = [
                'total_listings' => $listings->count(),
                'by_status' => [
                    'draft' => $listings->where('status', 'draft')->count(),
                    'published' => $listings->where('status', 'published')->count(),
                    'active' => $listings->where('status', 'active')->count(),
                    'sold' => $listings->where('status', 'sold')->count(),
                    'closed' => $listings->where('status', 'closed')->count(),
                ],
                'by_category' => [
                    'motorcycles' => $listings->where('category_id', 1)->count(),
                    'spare_parts' => $listings->where('category_id', 2)->count(),
                    'license_plates' => $listings->where('category_id', 3)->count(),
                ],
                'total_with_auctions' => $listings->where('auction_enabled', true)->count(),
                'total_with_submissions' => $listings->where('allow_submission', true)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedListings,
                'summary' => $summary
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "User with ID {$id} not found"
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error in UserController@getUserListings', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user listings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // /**
    //  * @OA\Get(
    //  *     path="/api/admin/users/{id}/bank-cards",
    //  *     operationId="getUserBankCards",
    //  *     tags={"Users Management"},
    //  *     summary="Get user's bank cards",
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         @OA\Schema(type="integer", example=1)
    //  *     ),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Successfully retrieved bank cards",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
    //  *         )
    //  *     )
    //  * )
    //  */
    // public function getUserBankCards(string $id)
    // {
    //     $user = User::findOrFail($id);
    //     $bankCards = $user->bankCards()->with('cardType')->get();

    //     return response()->json([
    //         'success' => true,
    //         'data' => $bankCards
    //     ]);
    // }

    // /**
    //  * @OA\Get(
    //  *     path="/api/admin/users/{id}/auction-history",
    //  *     operationId="getUserAuctionHistory",
    //  *     tags={"Users Management"},
    //  *     summary="Get user's auction history",
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         @OA\Schema(type="integer", example=1)
    //  *     ),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Successfully retrieved auction history",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
    //  *         )
    //  *     )
    //  * )
    //  */
    // public function getUserAuctionHistory(string $id)
    // {
    //     $user = User::findOrFail($id);
    //     $auctionHistory = DB::table('auction_histories')
    //         ->where('buyer_id', $id)
    //         ->join('listings', 'auction_histories.listing_id', '=', 'listings.id')
    //         ->select('auction_histories.*', 'listings.title as listing_title')
    //         ->orderBy('bid_date', 'desc')
    //         ->get();

    //     return response()->json([
    //         'success' => true,
    //         'data' => $auctionHistory
    //     ]);
    // }

    /**
     * @OA\Get(
     *     path="/admin/users/stats",
     *     operationId="getUsersStats",
     *     tags={"Users Management"},
     *     summary="Get users statistics",
     *     description="Returns detailed statistics about users, including total counts, active status, verification status, registration completion, and counts by role/country.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved users statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_users", type="integer", example=1250),
     *                 @OA\Property(property="active_users", type="integer", example=980),
     *                 @OA\Property(property="inactive_users", type="integer", example=270),
     *                 @OA\Property(property="verified_users", type="integer", example=750),
     *                 @OA\Property(property="unverified_users", type="integer", example=500),
     *                 @OA\Property(property="online_users", type="integer", example=45),
     *                 @OA\Property(property="registration_completed", type="integer", example=1100),
     *                 @OA\Property(property="new_users_today", type="integer", example=15),
     *                 @OA\Property(property="new_users_this_week", type="integer", example=87),
     *                 @OA\Property(property="new_users_this_month", type="integer", example=345),
     *                 @OA\Property(property="new_users_this_year", type="integer", example=1200),
     *                 @OA\Property(
     *                     property="users_by_role",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(
     *                     property="users_by_country",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function stats()
    {
        try {
            $totalUsers = User::count();
            $activeUsers = User::where('is_active', true)->count();
            $inactiveUsers = $totalUsers - $activeUsers;

            $verifiedUsers = User::where('verified', true)->count();
            $unverifiedUsers = $totalUsers - $verifiedUsers;

            $onlineUsers = User::where('is_online', true)->count();

            $registrationCompleted = User::where('is_registration_completed', true)->count();

            $newUsersToday = User::whereDate('created_at', Carbon::today())->count();
            $newUsersThisWeek = User::where('created_at', '>=', Carbon::now()->startOfWeek())->count();

            $newUsersThisMonth = User::whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->count();

            $newUsersThisYear = User::whereYear('created_at', Carbon::now()->year)->count();

            $usersByRole = User::select('role_id', DB::raw('count(*) as count'))
                ->with('role:id,name')
                ->groupBy('role_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'role_id' => $item->role_id,
                        'role_name' => $item->role->name ?? 'Unknown',
                        'count' => $item->count
                    ];
                });

            $usersByCountry = User::select('country_id', DB::raw('count(*) as count'))
                ->with('country:id,name')
                ->whereNotNull('country_id')
                ->groupBy('country_id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'country_id' => $item->country_id,
                        'country_name' => $item->country->name ?? 'Unknown',
                        'count' => $item->count
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'inactive_users' => $inactiveUsers,
                    'verified_users' => $verifiedUsers,
                    'unverified_users' => $unverifiedUsers,
                    'online_users' => $onlineUsers,
                    'registration_completed' => $registrationCompleted,
                    'new_users_today' => $newUsersToday,
                    'new_users_this_week' => $newUsersThisWeek,
                    'new_users_this_month' => $newUsersThisMonth,
                    'new_users_this_year' => $newUsersThisYear,
                    'users_by_role' => $usersByRole,
                    'users_by_country' => $usersByCountry,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/stats/detailed",
     *     operationId="getUsersDetailedStatistics",
     *     tags={"Users Management"},
     *     summary="Get detailed users statistics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=2024)
     *     ),
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved detailed statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="users_today", type="integer", example=15),
     *                 @OA\Property(property="users_this_week", type="integer", example=87),
     *                 @OA\Property(property="users_this_month", type="integer", example=345),
     *                 @OA\Property(property="growth_rate_percentage", type="number", example=12.5)
     *             )
     *         )
     *     )
     * )
     */
    public function detailedStats(Request $request)
    {
        $year = $request->get('year', Carbon::now()->year);
        $month = $request->get('month', Carbon::now()->month);

        $usersToday = User::whereDate('created_at', Carbon::today())->count();

        $usersThisWeek = User::whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ])->count();

        $usersByDay = User::select(
            DB::raw('DAY(created_at) as day'),
            DB::raw('COUNT(*) as count')
        )
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $usersByMonth = User::select(
            DB::raw('MONTH(created_at) as month'),
            DB::raw('COUNT(*) as count')
        )
            ->whereYear('created_at', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $usersByYear = User::select(
            DB::raw('YEAR(created_at) as year'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', Carbon::now()->subYears(5))
            ->groupBy('year')
            ->orderBy('year')
            ->get();

        $lastMonthUsers = User::whereYear('created_at', Carbon::now()->subMonth()->year)
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->count();

        $currentMonthUsers = User::whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();

        $growthRate = $lastMonthUsers > 0
            ? (($currentMonthUsers - $lastMonthUsers) / $lastMonthUsers) * 100
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'users_today' => $usersToday,
                'users_this_week' => $usersThisWeek,
                'users_this_month' => $currentMonthUsers,
                'users_by_day' => $usersByDay,
                'users_by_month' => $usersByMonth,
                'users_by_year' => $usersByYear,
                'growth_rate_percentage' => round($growthRate, 2),
                'comparison' => [
                    'last_month' => $lastMonthUsers,
                    'current_month' => $currentMonthUsers,
                    'difference' => $currentMonthUsers - $lastMonthUsers
                ]
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/users/bulk-action",
     *     operationId="usersBulkAction",
     *     tags={"Users Management"},
     *     summary="Perform bulk actions on multiple users",
     *     description="Execute actions (activate, deactivate, delete, verify, assign role) on multiple users at once",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_ids", "action"},
     *             @OA\Property(
     *                 property="user_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={2, 3, 4, 5},
     *                 description="Array of user IDs to perform action on"
     *             ),
     *             @OA\Property(
     *                 property="action",
     *                 type="string",
     *                 enum={"activate", "deactivate", "delete", "verify", "unverify", "assign_role", "send_email"},
     *                 example="activate",
     *                 description="Action to perform"
     *             ),
     *             @OA\Property(
     *                 property="role_id",
     *                 type="integer",
     *                 example=2,
     *                 description="Required when action is 'assign_role'"
     *             ),
     *             @OA\Property(
     *                 property="email_subject",
     *                 type="string",
     *                 example="Important Update",
     *                 description="Required when action is 'send_email'"
     *             ),
     *             @OA\Property(
     *                 property="email_message",
     *                 type="string",
     *                 example="Hello, this is an important message...",
     *                 description="Required when action is 'send_email'"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bulk action completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bulk activate completed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="affected_users", type="integer", example=5),
     *                 @OA\Property(property="failed_users", type="array", @OA\Items(type="integer")),
     *                 @OA\Property(property="action", type="string", example="activate")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function bulkAction(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'integer|exists:users,id',
                'action' => 'required|string|in:activate,deactivate,delete,verify,unverify,assign_role,send_email',
                'role_id' => 'required_if:action,assign_role|exists:roles,id',
                'email_subject' => 'required_if:action,send_email|string|max:255',
                'email_message' => 'required_if:action,send_email|string',
            ]);

            $userIds = $validated['user_ids'];
            $action = $validated['action'];

            // ⭐ Empêcher la modification de l'admin principal (ID 1)
            if (in_array(1, $userIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot perform bulk actions on the main administrator account (ID: 1)'
                ], 403);
            }

            // ⭐ Empêcher l'admin de se modifier lui-même
            $currentUserId = auth()->id();
            if (in_array($currentUserId, $userIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot perform bulk actions on your own account'
                ], 403);
            }

            $affectedUsers = 0;
            $failedUsers = [];

            switch ($action) {
                case 'activate':
                    $affectedUsers = User::whereIn('id', $userIds)->update(['is_active' => true]);
                    break;

                case 'deactivate':
                    $affectedUsers = User::whereIn('id', $userIds)->update(['is_active' => false]);
                    break;

                case 'delete':
                    // Soft delete
                    $affectedUsers = User::whereIn('id', $userIds)->delete();
                    break;

                case 'verify':
                    $affectedUsers = User::whereIn('id', $userIds)->update(['verified' => true]);
                    break;

                case 'unverify':
                    $affectedUsers = User::whereIn('id', $userIds)->update(['verified' => false]);
                    break;

                case 'assign_role':
                    $affectedUsers = User::whereIn('id', $userIds)
                        ->update(['role_id' => $validated['role_id']]);
                    break;

                case 'send_email':
                    $users = User::whereIn('id', $userIds)->get();
                    foreach ($users as $user) {
                        try {
                            Mail::raw($validated['email_message'], function ($message) use ($user, $validated) {
                                $message->to($user->email)
                                    ->subject($validated['email_subject']);
                            });
                            $affectedUsers++;
                        } catch (\Exception $e) {
                            $failedUsers[] = $user->id;
                            \Log::error('Failed to send bulk email to user ' . $user->id, [
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    break;
            }

            // ⭐ Logger l'action
            \Log::info('Bulk action performed', [
                'action' => $action,
                'performed_by' => auth()->id(),
                'affected_users' => $affectedUsers,
                'user_ids' => $userIds,
                'failed_users' => $failedUsers
            ]);

            return response()->json([
                'success' => true,
                'message' => "Bulk {$action} completed successfully",
                'data' => [
                    'action' => $action,
                    'affected_users' => $affectedUsers,
                    'failed_users' => $failedUsers,
                    'total_requested' => count($userIds)
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Error in bulkAction', [
                'error' => $e->getMessage(),
                'action' => $request->action ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error performing bulk action',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/export",
     *     operationId="exportUsers",
     *     tags={"Users Management"},
     *     summary="Export users to CSV",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="CSV file download"
     *     )
     * )
     */
    public function export()
    {
        $users = User::with('role')->get();

        $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($users) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID',
                'First Name',
                'Last Name',
                'Email',
                'Phone',
                'Birthday',
                'Gender',
                'Address',
                'Postal Code',
                'Role',
                'Country ID',
                'Is Active',
                'Verified',
                'Created At'
            ]);

            foreach ($users as $user) {
                fputcsv($file, [
                    $user->id,
                    $user->first_name,
                    $user->last_name,
                    $user->email,
                    $user->phone,
                    $user->birthday,
                    $user->gender,
                    $user->address,
                    $user->postal_code,
                    $user->role->name ?? 'N/A',
                    $user->country_id,
                    $user->is_active ? 'Yes' : 'No',
                    $user->verified ? 'Yes' : 'No',
                    $user->created_at
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/trashed",
     *     operationId="getTrashedUsers",
     *     tags={"Users Management"},
     *     summary="Get soft-deleted users",
     *     description="Returns paginated list of soft-deleted users with their role and country information",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (default: 15, max: 100)",
     *         required=false,
     *         @OA\Schema(type="integer", example=15, minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name, email or phone",
     *         required=false,
     *         @OA\Schema(type="string", example="john")
     *     ),
     *     @OA\Parameter(
     *         name="role_id",
     *         in="query",
     *         description="Filter by role ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field (deleted_at, first_name, email)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"deleted_at", "first_name", "email"}, example="deleted_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved trashed users",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="first_name", type="string", example="John"),
     *                         @OA\Property(property="last_name", type="string", example="Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com"),
     *                         @OA\Property(property="phone", type="string", example="+212695388904"),
     *                         @OA\Property(property="role_id", type="integer", example=2),
     *                         @OA\Property(property="is_active", type="boolean", example=false),
     *                         @OA\Property(property="verified", type="boolean", example=true),
     *                         @OA\Property(property="deleted_at", type="string", format="date-time", example="2025-01-13T14:30:00.000000Z"),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time"),
     *                         @OA\Property(
     *                             property="role",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Admin")
     *                         ),
     *                         @OA\Property(
     *                             property="country",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Morocco"),
     *                             @OA\Property(property="code", type="string", example="MA")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=45),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Missing permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="You don't have permission to view trashed users")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="per_page",
     *                     type="array",
     *                     @OA\Items(type="string", example="The per page must not be greater than 100.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getTrashed(Request $request)
    {
        // Validation
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:255',
            'role_id' => 'nullable|integer|exists:roles,id',
            'sort_by' => 'nullable|string|in:deleted_at,first_name,email,created_at',
            'sort_order' => 'nullable|string|in:asc,desc'
        ]);

        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $roleId = $request->get('role_id');
        $sortBy = $request->get('sort_by', 'deleted_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query = User::onlyTrashed()
            ->with(['role', 'role.permissions', 'country']);

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($roleId) {
            $query->where('role_id', $roleId);
        }

        // Sorting
        $query->orderBy($sortBy, $sortOrder);

        $trashedUsers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $trashedUsers
        ]);
    }
    /**
     * @OA\Post(
     *     path="/api/admin/users/search",
     *     operationId="searchUsers",
     *     tags={"Users Management"},
     *     summary="Advanced user search",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="search", type="string", example="john"),
     *             @OA\Property(property="role_id", type="integer", example=2),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="verified", type="boolean", example=true),
     *             @OA\Property(property="created_from", type="string", format="date", example="2024-01-01"),
     *             @OA\Property(property="created_to", type="string", format="date", example="2024-12-31")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function search(Request $request)
    {
        $query = User::with(['role', 'country']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->has('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('verified')) {
            $query->where('verified', $request->verified);
        }

        if ($request->has('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }

        if ($request->has('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        $users = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }
    /**
     * @OA\Post(
     *     path="/api/admin/users/{id}/toggle-verified",
     *     operationId="toggleUserVerified",
     *     tags={"Users Management"},
     *     summary="Toggle user's verified status",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully toggled verified status",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="verified", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User with ID 1 not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error toggling verified status"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function toggleVerified($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->verified = !$user->verified;
            $user->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'verified' => $user->verified
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "User with ID {$id} not found"
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error in UserController@toggleVerified', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error toggling verified status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //swagger for api for toggle actif or not actif
    /**
     * @OA\Post(
     *     path="/api/admin/users/{id}/toggle-active",
     *     operationId="toggleUserActive",
     *     tags={"Users Management"},
     *     summary="Toggle user's active status",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully toggled active status",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User with ID 1 not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error toggling active status"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function toggleActive($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->is_active = !$user->is_active;
            $user->save();
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'is_active' => $user->is_active
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "User with ID {$id} not found"
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error in UserController@toggleActive', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error toggling active status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/admin/users/authentication-logs",
     *     operationId="getAuthenticationLogs",
     *     tags={"Users Management"},
     *     summary="Get authentication logs with filters",
     *     description="Returns paginated authentication logs. Supports filtering by user, date range, online status, and searching.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15, default=15)
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="is_online",
     *         in="query",
     *         description="Filter by online status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by user name or email",
     *         required=false,
     *         @OA\Schema(type="string", example="john")
     *     ),
     *     @OA\Parameter(
     *         name="connection_from",
     *         in="query",
     *         description="Filter by connection date from",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="connection_to",
     *         in="query",
     *         description="Filter by connection date to",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="token_expired",
     *         in="query",
     *         description="Filter by token expiration status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string", example="connection_date", default="connection_date")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc", default="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved authentication logs",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="first_name", type="string", example="John"),
     *                             @OA\Property(property="last_name", type="string", example="Doe"),
     *                             @OA\Property(property="email", type="string", example="john@example.com"),
     *                             @OA\Property(property="profile_picture", type="string", example="profiles/user.jpg")
     *                         ),
     *                         @OA\Property(property="is_online", type="boolean", example=true),
     *                         @OA\Property(property="connection_date", type="string", example="2024-12-04 18:44:52"),
     *                         @OA\Property(property="token_expiration", type="string", example="2024-12-04 19:44:52"),
     *                         @OA\Property(property="refresh_token_expiration", type="string", example="2026-01-03 18:44:52"),
     *                         @OA\Property(property="is_token_expired", type="boolean", example=false),
     *                         @OA\Property(property="is_refresh_token_expired", type="boolean", example=false),
     *                         @OA\Property(property="session_duration", type="string", example="2 hours 15 minutes"),
     *                         @OA\Property(property="created_at", type="string", example="2024-12-04 18:44:52"),
     *                         @OA\Property(property="updated_at", type="string", example="2024-12-04 18:44:52")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="stats",
     *                 type="object",
     *                 @OA\Property(property="total_logs", type="integer", example=100),
     *                 @OA\Property(property="online_users", type="integer", example=45),
     *                 @OA\Property(property="expired_tokens", type="integer", example=10),
     *                 @OA\Property(property="active_sessions", type="integer", example=90)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Missing permission")
     * )
     */
    public function getAuthenticationLogs(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $userId = $request->get('user_id');
            $isOnline = $request->get('is_online');
            $connectionFrom = $request->get('connection_from');
            $connectionTo = $request->get('connection_to');
            $tokenExpired = $request->get('token_expired');
            $sortBy = $request->get('sort_by', 'connection_date');
            $sortOrder = $request->get('sort_order', 'desc');

            $query = Authentication::with([
                'user:id,first_name,last_name,email,profile_picture,role_id',
                'user.role:id,name'
            ]);

            // Filter by user ID
            if ($userId) {
                $query->where('user_id', $userId);
            }

            // Filter by online status
            if ($isOnline !== null && $isOnline !== '') {
                $isOnlineBool = filter_var($isOnline, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($isOnlineBool !== null) {
                    $query->where('is_online', $isOnlineBool);
                }
            }

            // Search by user name or email
            if ($search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            // Filter by connection date range
            if ($connectionFrom) {
                $query->whereDate('connection_date', '>=', $connectionFrom);
            }
            if ($connectionTo) {
                $query->whereDate('connection_date', '<=', $connectionTo);
            }

            // Filter by token expiration status
            if ($tokenExpired !== null && $tokenExpired !== '') {
                $tokenExpiredBool = filter_var($tokenExpired, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($tokenExpiredBool !== null) {
                    if ($tokenExpiredBool) {
                        // Token expiré
                        $query->where('token_expiration', '<', Carbon::now());
                    } else {
                        // Token valide
                        $query->where('token_expiration', '>=', Carbon::now());
                    }
                }
            }

            // Sorting
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $logs = $query->paginate($perPage);

            // Format les données
            $formattedLogs = $logs->map(function ($log) {
                $tokenExpiration = Carbon::parse($log->token_expiration);
                $refreshTokenExpiration = Carbon::parse($log->refresh_token_expiration);
                $now = Carbon::now();

                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'first_name' => $log->user->first_name,
                        'last_name' => $log->user->last_name,
                        'full_name' => $log->user->first_name . ' ' . $log->user->last_name,
                        'email' => $log->user->email,
                        'profile_picture' => $log->user->profile_picture,
                        'role' => $log->user->role?->name,
                    ] : null,
                    'is_online' => $log->is_online,
                    'connection_date' => $log->connection_date ? $log->connection_date->format('Y-m-d H:i:s') : null,
                    'token_expiration' => $tokenExpiration->format('Y-m-d H:i:s'),
                    'refresh_token_expiration' => $refreshTokenExpiration->format('Y-m-d H:i:s'),
                    'is_token_expired' => $tokenExpiration->isPast(),
                    'is_refresh_token_expired' => $refreshTokenExpiration->isPast(),
                    'token_expires_in' => $tokenExpiration->isFuture()
                        ? $tokenExpiration->diffForHumans($now, true)
                        : 'Expired',
                    'refresh_token_expires_in' => $refreshTokenExpiration->isFuture()
                        ? $refreshTokenExpiration->diffForHumans($now, true)
                        : 'Expired',
                    'session_duration' => $log->connection_date
                        ? $log->connection_date->diffForHumans($now, true)
                        : null,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $log->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            // Statistiques
            $stats = [
                'total_logs' => Authentication::count(),
                'online_users' => Authentication::where('is_online', true)->count(),
                'expired_tokens' => Authentication::where('token_expiration', '<', Carbon::now())->count(),
                'active_sessions' => Authentication::where('token_expiration', '>=', Carbon::now())
                    ->where('is_online', true)
                    ->count(),
                'total_unique_users' => Authentication::distinct('user_id')->count('user_id'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                    'data' => $formattedLogs,
                ],
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in UserController@getAuthenticationLogs', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving authentication logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/{id}/authentication-logs",
     *     operationId="getUserAuthenticationLogs",
     *     tags={"Users Management"},
     *     summary="Get authentication logs for a specific user",
     *     description="Returns all authentication logs for a specific user with statistics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15, default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved user authentication logs",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getUserAuthenticationLogs(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);
            $perPage = $request->get('per_page', 15);

            $logs = Authentication::where('user_id', $id)
                ->orderBy('connection_date', 'desc')
                ->paginate($perPage);

            // Format les données
            $formattedLogs = $logs->map(function ($log) {
                $tokenExpiration = Carbon::parse($log->token_expiration);
                $refreshTokenExpiration = Carbon::parse($log->refresh_token_expiration);
                $now = Carbon::now();

                return [
                    'id' => $log->id,
                    'is_online' => $log->is_online,
                    'connection_date' => $log->connection_date ? $log->connection_date->format('Y-m-d H:i:s') : null,
                    'token_expiration' => $tokenExpiration->format('Y-m-d H:i:s'),
                    'refresh_token_expiration' => $refreshTokenExpiration->format('Y-m-d H:i:s'),
                    'is_token_expired' => $tokenExpiration->isPast(),
                    'is_refresh_token_expired' => $refreshTokenExpiration->isPast(),
                    'token_expires_in' => $tokenExpiration->isFuture()
                        ? $tokenExpiration->diffForHumans($now, true)
                        : 'Expired',
                    'session_duration' => $log->connection_date
                        ? $log->connection_date->diffForHumans($now, true)
                        : null,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $log->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            // Statistiques pour cet utilisateur
            $stats = [
                'total_logins' => Authentication::where('user_id', $id)->count(),
                'currently_online' => $user->is_online,
                'last_login' => $user->last_login ? $user->last_login->format('Y-m-d H:i:s') : null,
                'active_sessions' => Authentication::where('user_id', $id)
                    ->where('token_expiration', '>=', Carbon::now())
                    ->where('is_online', true)
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                    ],
                    'logs' => [
                        'current_page' => $logs->currentPage(),
                        'per_page' => $logs->perPage(),
                        'total' => $logs->total(),
                        'last_page' => $logs->lastPage(),
                        'data' => $formattedLogs,
                    ],
                    'stats' => $stats
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "User with ID {$id} not found"
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error in UserController@getUserAuthenticationLogs', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user authentication logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/users/{id}/assign-poi",
     *     summary="Assign a POI to a user (makes the user a dealer)",
     *     tags={"Users Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID to become owner of the POI",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"poi_id"},
     *             @OA\Property(property="poi_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="POI assigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="POI assigned to user successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User or POI not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */

    public function assignPoi(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $request->validate([
                'poi_id' => 'required|exists:points_of_interest,id',
            ]);

            $poi = PointOfInterest::find($request->poi_id);
            $poi->owner_id = $user->id;
            $poi->save();

            return response()->json([
                'success' => true,
                'message' => 'POI assigned to user successfully. This user is now considered a dealer.',
                'data' => [
                    'user_id' => (int) $user->id,
                    'poi_id' => (int) $poi->id,
                    'is_dealer' => true
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign POI: ' . $e->getMessage()
            ], 500);
        }
    }
}
