<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Mail\UserCreatedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

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

        $query = User::with(['role', 'country']);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        if ($roleId) {
            $query->where('role_id', $roleId);
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($verified !== null) {
            $query->where('verified', $verified);
        }

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users
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
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'nullable|string|unique:users,phone',
            'birthday'   => 'nullable|date',
            'gender'     => 'nullable|string|in:male,female,other',
            'profile_picture' => 'nullable|string',
            'address'    => 'nullable|string',
            'postal_code' => 'nullable|string',
            'role_id'    => 'required|exists:roles,id',
            'country_id' => 'nullable|exists:countries,id',
            'language'   => 'nullable|string|max:10',
            'timezone'   => 'nullable|string|max:50',
        ]);

        $generatedPassword = Str::random(12);
        $validated['password'] = Hash::make($generatedPassword);
        $validated['is_active'] = true;
        $validated['verified'] = false;

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
     *     summary="Get user details by ID",
     *     description="Returns detailed user information including role, country, bank cards, wishlists, listings, and statistics.",
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
     *         description="Successfully retrieved user details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="first_name", type="string", example="John"),
     *                     @OA\Property(property="last_name", type="string", example="Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com")
     *                 ),
     *                 @OA\Property(
     *                     property="stats",
     *                     type="object",
     *                     @OA\Property(property="total_listings", type="integer", example=5),
     *                     @OA\Property(property="active_listings", type="integer", example=3),
     *                     @OA\Property(property="total_wishlists", type="integer", example=10),
     *                     @OA\Property(property="total_bank_cards", type="integer", example=2),
     *                     @OA\Property(property="auction_participations", type="integer", example=15),
     *                     @OA\Property(property="auction_wins", type="integer", example=5)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Missing permission")
     * )
     */
    public function show(string $id)
    {
        $user = User::with([
            'role',
            'country',
            'bankCards',
            'wishlists.listing',
            'listings' => function($query) {
                $query->withCount('listingImages');
            }
        ])->findOrFail($id);

        $userStats = [
            'total_listings' => $user->listings()->count(),
            'active_listings' => $user->listings()->where('status', 'active')->count(),
            'total_wishlists' => $user->wishlists()->count(),
            'total_bank_cards' => $user->bankCards()->count(),
            'auction_participations' => DB::table('auction_histories')->where('buyer_id', $id)->count(),
            'auction_wins' => DB::table('auction_histories')->where('buyer_id', $id)->where('validated', 1)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'stats' => $userStats
            ]
        ]);
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
     *             @OA\Property(property="timezone", type="string", example="Europe/Paris")
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
            'last_name'  => 'sometimes|required|string|max:255',
            'email'      => 'sometimes|required|email|unique:users,email,' . $id,
            'phone'      => 'nullable|string|unique:users,phone,' . $id,
            'birthday'   => 'nullable|date',
            'gender'     => 'nullable|string|in:male,female,other',
            'profile_picture' => 'nullable|string',
            'address'    => 'nullable|string',
            'postal_code' => 'nullable|string',
            'role_id'    => 'sometimes|required|exists:roles,id',
            'country_id' => 'nullable|exists:countries,id',
            'language'   => 'nullable|string|max:10',
            'timezone'   => 'nullable|string|max:50',
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
     *     summary="Get user's listings",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved listings",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getUserListings(string $id)
    {
        $user = User::findOrFail($id);
        $listings = $user->listings()->with(['category', 'listingImages'])->get();

        return response()->json([
            'success' => true,
            'data' => $listings
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/{id}/bank-cards",
     *     operationId="getUserBankCards",
     *     tags={"Users Management"},
     *     summary="Get user's bank cards",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved bank cards",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getUserBankCards(string $id)
    {
        $user = User::findOrFail($id);
        $bankCards = $user->bankCards()->with('cardType')->get();

        return response()->json([
            'success' => true,
            'data' => $bankCards
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/{id}/auction-history",
     *     operationId="getUserAuctionHistory",
     *     tags={"Users Management"},
     *     summary="Get user's auction history",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved auction history",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getUserAuctionHistory(string $id)
    {
        $user = User::findOrFail($id);
        $auctionHistory = DB::table('auction_histories')
            ->where('buyer_id', $id)
            ->join('listings', 'auction_histories.listing_id', '=', 'listings.id')
            ->select('auction_histories.*', 'listings.title as listing_title')
            ->orderBy('bid_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $auctionHistory
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/stats",
     *     operationId="getUsersStatistics",
     *     tags={"Users Management"},
     *     summary="Get users statistics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_users", type="integer", example=1250),
     *                 @OA\Property(property="active_users", type="integer", example=980),
     *                 @OA\Property(property="verified_users", type="integer", example=750),
     *                 @OA\Property(property="online_users", type="integer", example=45)
     *             )
     *         )
     *     )
     * )
     */
    public function stats()
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $verifiedUsers = User::where('verified', true)->count();
        $onlineUsers = User::where('is_online', true)->count();

        $usersByRole = User::select('role_id', DB::raw('count(*) as count'))
            ->with('role:id,name')
            ->groupBy('role_id')
            ->get()
            ->map(function($item) {
                return [
                    'role_id' => $item->role_id,
                    'role_name' => $item->role->name ?? 'Unknown',
                    'count' => $item->count
                ];
            });

        $newUsersThisMonth = User::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $newUsersThisYear = User::whereYear('created_at', Carbon::now()->year)->count();

        $usersByCountry = User::select('country_id', DB::raw('count(*) as count'))
            ->with('country:id,name')
            ->whereNotNull('country_id')
            ->groupBy('country_id')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->map(function($item) {
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
                'verified_users' => $verifiedUsers,
                'online_users' => $onlineUsers,
                'users_by_role' => $usersByRole,
                'new_users_this_month' => $newUsersThisMonth,
                'new_users_this_year' => $newUsersThisYear,
                'users_by_country' => $usersByCountry,
            ]
        ]);
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
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_ids", "action"},
     *             @OA\Property(
     *                 property="user_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3, 4, 5}
     *             ),
     *             @OA\Property(
     *                 property="action",
     *                 type="string",
     *                 enum={"activate", "deactivate", "delete", "verify"},
     *                 example="activate"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bulk action completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bulk activate completed successfully")
     *         )
     *     )
     * )
     */
    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'action' => 'required|string|in:activate,deactivate,delete,verify'
        ]);

        $userIds = $validated['user_ids'];
        $action = $validated['action'];

        switch ($action) {
            case 'activate':
                User::whereIn('id', $userIds)->update(['is_active' => true]);
                break;
            case 'deactivate':
                User::whereIn('id', $userIds)->update(['is_active' => false]);
                break;
            case 'delete':
                User::whereIn('id', $userIds)->delete();
                break;
            case 'verify':
                User::whereIn('id', $userIds)->update(['verified' => true]);
                break;
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk {$action} completed successfully"
        ]);
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

        $callback = function() use ($users) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID', 'First Name', 'Last Name', 'Email', 'Phone',
                'Birthday', 'Gender', 'Address', 'Postal Code',
                'Role', 'Country ID', 'Is Active', 'Verified',
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
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved trashed users",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getTrashed(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $trashedUsers = User::onlyTrashed()
            ->with(['role', 'country'])
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);

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
            $query->where(function($q) use ($search) {
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
}
