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
use Carbon\Carbon;

class UserController extends Controller
{
    // /**
    //  * @OA\Get(
    //  *     path="/api/users",
    //  *     summary="Get list of users with pagination and filters",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="page",
    //  *         in="query",
    //  *         description="Page number",
    //  *         @OA\Schema(type="integer")
    //  *     ),
    //  *     @OA\Parameter(
    //  *         name="per_page",
    //  *         in="query",
    //  *         description="Items per page",
    //  *         @OA\Schema(type="integer")
    //  *     ),
    //  *     @OA\Parameter(
    //  *         name="search",
    //  *         in="query",
    //  *         description="Search by name or email",
    //  *         @OA\Schema(type="string")
    //  *     ),
    //  *     @OA\Parameter(
    //  *         name="role_id",
    //  *         in="query",
    //  *         description="Filter by role",
    //  *         @OA\Schema(type="integer")
    //  *     ),
    //  *     @OA\Parameter(
    //  *         name="is_active",
    //  *         in="query",
    //  *         description="Filter by active status",
    //  *         @OA\Schema(type="boolean")
    //  *     ),
    //  *     @OA\Parameter(
    //  *         name="sort_by",
    //  *         in="query",
    //  *         description="Sort by field",
    //  *         @OA\Schema(type="string")
    //  *     ),
    //  *     @OA\Parameter(
    //  *         name="sort_order",
    //  *         in="query",
    //  *         description="Sort order (asc/desc)",
    //  *         @OA\Schema(type="string")
    //  *     ),
    //  *     @OA\Response(response=200, description="Successfully retrieved list of users"),
    //  *     @OA\Response(response=401, description="Unauthorized"),
    //  *     @OA\Response(response=500, description="Internal Server Error")
    //  * )
    //  */
    // public function index(Request $request)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $perPage = $request->get('per_page', 15);
    //     $search = $request->get('search');
    //     $roleId = $request->get('role_id');
    //     $isActive = $request->get('is_active');
    //     $sortBy = $request->get('sort_by', 'created_at');
    //     $sortOrder = $request->get('sort_order', 'desc');

    //     $query = User::query();

    //     // Search
    //     if ($search) {
    //         $query->where(function($q) use ($search) {
    //             $q->where('first_name', 'LIKE', "%{$search}%")
    //               ->orWhere('last_name', 'LIKE', "%{$search}%")
    //               ->orWhere('email', 'LIKE', "%{$search}%")
    //               ->orWhere('phone', 'LIKE', "%{$search}%");
    //         });
    //     }

    //     // Filter by role
    //     if ($roleId) {
    //         $query->where('role_id', $roleId);
    //     }

    //     // Filter by active status
    //     if ($isActive !== null) {
    //         $query->where('is_active', $isActive);
    //     }

    //     // Sorting
    //     $query->orderBy($sortBy, $sortOrder);

    //     $users = $query->paginate($perPage);

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $users
    //     ]);
    // }

    // /**
    //  * @OA\Post(
    //  *     path="/api/users",
    //  *     summary="Create a new user",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\MediaType(
    //  *             mediaType="application/json",
    //  *             @OA\Schema(
    //  *                 type="object",
    //  *                 required={"first_name", "last_name", "email", "role_id"},
    //  *                 @OA\Property(property="first_name", type="string"),
    //  *                 @OA\Property(property="last_name", type="string"),
    //  *                 @OA\Property(property="email", type="string"),
    //  *                 @OA\Property(property="phone", type="string"),
    //  *                 @OA\Property(property="birthday", type="string", format="date"),
    //  *                 @OA\Property(property="gender", type="string"),
    //  *                 @OA\Property(property="profile_picture", type="string"),
    //  *                 @OA\Property(property="address", type="string"),
    //  *                 @OA\Property(property="postal_code", type="string"),
    //  *                 @OA\Property(property="role_id", type="integer"),
    //  *                 @OA\Property(property="country_id", type="integer"),
    //  *                 @OA\Property(property="language", type="string"),
    //  *                 @OA\Property(property="timezone", type="string")
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=201, description="User created successfully"),
    //  *     @OA\Response(response=400, description="Bad Request"),
    //  *     @OA\Response(response=401, description="Unauthorized"),
    //  *     @OA\Response(response=500, description="Internal Server Error")
    //  * )
    //  */
    // public function store(Request $request)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $validated = $request->validate([
    //         'first_name' => 'required|string|max:255',
    //         'last_name'  => 'required|string|max:255',
    //         'email'      => 'required|email|unique:users,email',
    //         'phone'      => 'nullable|string|unique:users,phone',
    //         'birthday'   => 'nullable|date',
    //         'gender'     => 'nullable|string|in:male,female,other',
    //         'profile_picture' => 'nullable|string',
    //         'address'    => 'nullable|string',
    //         'postal_code' => 'nullable|string',
    //         'role_id'    => 'required|exists:roles,id',
    //         'country_id' => 'nullable|exists:countries,id',
    //         'language'   => 'nullable|string',
    //         'timezone'   => 'nullable|string',
    //     ]);

    //     // Auto-generate and hash password
    //     $generatedPassword = Str::random(12);
    //     $validated['password'] = Hash::make($generatedPassword);
    //     $validated['is_active'] = true;

    //     $user = User::create($validated);

    //     // Send email to user
    //     try {
    //         Mail::to($user->email)->send(new UserCreatedMail($user, $generatedPassword));
    //     } catch (\Exception $e) {
    //         // Log error but don't fail user creation
    //         \Log::error('Failed to send user creation email: ' . $e->getMessage());
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'User created successfully',
    //         'data' => $user
    //     ], 201);
    // }

    // /**
    //  * @OA\Get(
    //  *     path="/api/users/{id}",
    //  *     summary="Get user by ID",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID of the user",
    //  *         @OA\Schema(type="string")
    //  *     ),
    //  *     @OA\Response(response=200, description="Successfully retrieved user"),
    //  *     @OA\Response(response=404, description="User not found"),
    //  *     @OA\Response(response=401, description="Unauthorized"),
    //  *     @OA\Response(response=500, description="Internal Server Error")
    //  * )
    //  */
    // public function show(string $id)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $user = User::with(['bankCards', 'wishlists', 'listings'])->findOrFail($id);

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $user
    //     ]);
    // }

    // /**
    //  * @OA\Put(
    //  *     path="/api/users/{id}",
    //  *     summary="Update user details",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID of the user",
    //  *         @OA\Schema(type="string")
    //  *     ),
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\MediaType(
    //  *             mediaType="application/json",
    //  *             @OA\Schema(
    //  *                 type="object",
    //  *                 @OA\Property(property="first_name", type="string"),
    //  *                 @OA\Property(property="last_name", type="string"),
    //  *                 @OA\Property(property="email", type="string"),
    //  *                 @OA\Property(property="phone", type="string"),
    //  *                 @OA\Property(property="birthday", type="string", format="date"),
    //  *                 @OA\Property(property="gender", type="string"),
    //  *                 @OA\Property(property="profile_picture", type="string"),
    //  *                 @OA\Property(property="address", type="string"),
    //  *                 @OA\Property(property="postal_code", type="string"),
    //  *                 @OA\Property(property="role_id", type="integer"),
    //  *                 @OA\Property(property="country_id", type="integer"),
    //  *                 @OA\Property(property="language", type="string"),
    //  *                 @OA\Property(property="timezone", type="string")
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=200, description="User updated successfully"),
    //  *     @OA\Response(response=404, description="User not found"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function update(Request $request, string $id)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $user = User::findOrFail($id);

    //     $validated = $request->validate([
    //         'first_name' => 'sometimes|required|string|max:255',
    //         'last_name'  => 'sometimes|required|string|max:255',
    //         'email'      => 'sometimes|required|email|unique:users,email,' . $id,
    //         'phone'      => 'nullable|string|unique:users,phone,' . $id,
    //         'birthday'   => 'nullable|date',
    //         'gender'     => 'nullable|string|in:male,female,other',
    //         'profile_picture' => 'nullable|string',
    //         'address'    => 'nullable|string',
    //         'postal_code' => 'nullable|string',
    //         'role_id'    => 'sometimes|required|exists:roles,id',
    //         'country_id' => 'nullable|exists:countries,id',
    //         'language'   => 'nullable|string',
    //         'timezone'   => 'nullable|string',
    //     ]);

    //     $user->update($validated);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'User updated successfully',
    //         'data' => $user
    //     ]);
    // }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     summary="Delete user",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="User deleted successfully"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroy(string $id)
    {
        $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

        $user = User::findOrFail($id);

        // Suppression définitive de la base de données (pas de soft delete)
        $user->forceDelete();

        return response()->json([
            'status' => 'success',
            'message' => 'User permanently deleted successfully'
        ]);
    }

    // /**
    //  * @OA\Put(
    //  *     path="/api/users/{id}/deactivate",
    //  *     summary="Deactivate user account",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID of the user",
    //  *         @OA\Schema(type="string")
    //  *     ),
    //  *     @OA\Response(response=200, description="User deactivated successfully"),
    //  *     @OA\Response(response=404, description="User not found"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function deactivate(string $id)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $user = User::findOrFail($id);
    //     $user->update(['is_active' => false]);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'User deactivated successfully'
    //     ]);
    // }

    // /**
    //  * @OA\Put(
    //  *     path="/api/users/{id}/activate",
    //  *     summary="Activate user account",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID of the user",
    //  *         @OA\Schema(type="string")
    //  *     ),
    //  *     @OA\Response(response=200, description="User activated successfully"),
    //  *     @OA\Response(response=404, description="User not found"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function activate(string $id)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $user = User::findOrFail($id);
    //     $user->update(['is_active' => true]);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'User activated successfully'
    //     ]);
    // }

    // /**
    //  * @OA\Post(
    //  *     path="/api/users/{id}/reset-password",
    //  *     summary="Reset user password",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID of the user",
    //  *         @OA\Schema(type="string")
    //  *     ),
    //  *     @OA\Response(response=200, description="Password reset email sent"),
    //  *     @OA\Response(response=404, description="User not found"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function resetPassword(string $id)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $user = User::findOrFail($id);

    //     // Generate new password
    //     $newPassword = Str::random(12);
    //     $user->update(['password' => Hash::make($newPassword)]);

    //     // Send email with new password
    //     try {
    //         Mail::to($user->email)->send(new UserCreatedMail($user, $newPassword));
    //     } catch (\Exception $e) {
    //         \Log::error('Failed to send password reset email: ' . $e->getMessage());
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Password reset email sent successfully'
    //     ]);
    // }

    // /**
    //  * @OA\Get(
    //  *     path="/api/users/stats",
    //  *     summary="Get user statistics",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Response(response=200, description="Successfully retrieved user stats"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function stats()
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $totalUsers = User::count();
    //     $activeUsers = User::where('is_active', true)->count();
    //     $verifiedUsers = User::where('verified', true)->count();
    //     $onlineUsers = User::where('is_online', true)->count();

    //     // Users by role
    //     $usersByRole = User::select('role_id', DB::raw('count(*) as count'))
    //         ->groupBy('role_id')
    //         ->get();

    //     // New users this month
    //     $newUsersThisMonth = User::whereMonth('created_at', Carbon::now()->month)
    //         ->whereYear('created_at', Carbon::now()->year)
    //         ->count();

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => [
    //             'total_users' => $totalUsers,
    //             'active_users' => $activeUsers,
    //             'verified_users' => $verifiedUsers,
    //             'online_users' => $onlineUsers,
    //             'users_by_role' => $usersByRole,
    //             'new_users_this_month' => $newUsersThisMonth
    //         ]
    //     ]);
    // }

    // /**
    //  * @OA\Post(
    //  *     path="/api/users/search",
    //  *     summary="Advanced search for users",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\MediaType(
    //  *             mediaType="application/json",
    //  *             @OA\Schema(
    //  *                 type="object",
    //  *                 @OA\Property(property="search", type="string"),
    //  *                 @OA\Property(property="role_id", type="integer"),
    //  *                 @OA\Property(property="country_id", type="integer"),
    //  *                 @OA\Property(property="is_active", type="boolean"),
    //  *                 @OA\Property(property="verified", type="boolean"),
    //  *                 @OA\Property(property="created_from", type="string", format="date"),
    //  *                 @OA\Property(property="created_to", type="string", format="date")
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=200, description="Search results"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function search(Request $request)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $query = User::query();

    //     if ($request->has('search')) {
    //         $search = $request->search;
    //         $query->where(function($q) use ($search) {
    //             $q->where('first_name', 'LIKE', "%{$search}%")
    //               ->orWhere('last_name', 'LIKE', "%{$search}%")
    //               ->orWhere('email', 'LIKE', "%{$search}%");
    //         });
    //     }

    //     if ($request->has('role_id')) {
    //         $query->where('role_id', $request->role_id);
    //     }

    //     if ($request->has('country_id')) {
    //         $query->where('country_id', $request->country_id);
    //     }

    //     if ($request->has('is_active')) {
    //         $query->where('is_active', $request->is_active);
    //     }

    //     if ($request->has('verified')) {
    //         $query->where('verified', $request->verified);
    //     }

    //     if ($request->has('created_from')) {
    //         $query->whereDate('created_at', '>=', $request->created_from);
    //     }

    //     if ($request->has('created_to')) {
    //         $query->whereDate('created_at', '<=', $request->created_to);
    //     }

    //     $users = $query->paginate(15);

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $users
    //     ]);
    // }

    // /**
    //  * @OA\Post(
    //  *     path="/api/users/bulk-action",
    //  *     summary="Perform bulk actions on users",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\MediaType(
    //  *             mediaType="application/json",
    //  *             @OA\Schema(
    //  *                 type="object",
    //  *                 required={"user_ids", "action"},
    //  *                 @OA\Property(property="user_ids", type="array", @OA\Items(type="integer")),
    //  *                 @OA\Property(property="action", type="string", enum={"activate", "deactivate", "delete", "verify"})
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=200, description="Bulk action completed successfully"),
    //  *     @OA\Response(response=400, description="Bad Request"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function bulkAction(Request $request)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $validated = $request->validate([
    //         'user_ids' => 'required|array',
    //         'user_ids.*' => 'exists:users,id',
    //         'action' => 'required|string|in:activate,deactivate,delete,verify'
    //     ]);

    //     $userIds = $validated['user_ids'];
    //     $action = $validated['action'];

    //     switch ($action) {
    //         case 'activate':
    //             User::whereIn('id', $userIds)->update(['is_active' => true]);
    //             break;
    //         case 'deactivate':
    //             User::whereIn('id', $userIds)->update(['is_active' => false]);
    //             break;
    //         case 'delete':
    //             User::whereIn('id', $userIds)->delete();
    //             break;
    //         case 'verify':
    //             User::whereIn('id', $userIds)->update(['verified' => true]);
    //             break;
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => "Bulk {$action} completed successfully"
    //     ]);
    // }

    // /**
    //  * @OA\Get(
    //  *     path="/api/users/export",
    //  *     summary="Export users to CSV",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Response(response=200, description="Users exported successfully"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function export()
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $users = User::all();

    //     $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
    //     $headers = [
    //         'Content-Type' => 'text/csv',
    //         'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    //     ];

    //     $callback = function() use ($users) {
    //         $file = fopen('php://output', 'w');

    //         // Headers
    //         fputcsv($file, [
    //             'ID', 'First Name', 'Last Name', 'Email', 'Phone',
    //             'Birthday', 'Gender', 'Address', 'Postal Code',
    //             'Role ID', 'Country ID', 'Is Active', 'Verified',
    //             'Created At'
    //         ]);

    //         foreach ($users as $user) {
    //             fputcsv($file, [
    //                 $user->id,
    //                 $user->first_name,
    //                 $user->last_name,
    //                 $user->email,
    //                 $user->phone,
    //                 $user->birthday,
    //                 $user->gender,
    //                 $user->address,
    //                 $user->postal_code,
    //                 $user->role_id,
    //                 $user->country_id,
    //                 $user->is_active ? 'Yes' : 'No',
    //                 $user->verified ? 'Yes' : 'No',
    //                 $user->created_at
    //             ]);
    //         }

    //         fclose($file);
    //     };

    //     return response()->stream($callback, 200, $headers);
    // }

    // /**
    //  * @OA\Post(
    //  *     path="/api/users/{id}/two-factor/enable",
    //  *     summary="Enable two-factor authentication",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID of the user",
    //  *         @OA\Schema(type="integer")
    //  *     ),
    //  *     @OA\Response(response=200, description="Two-factor authentication enabled"),
    //  *     @OA\Response(response=404, description="User not found"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function enableTwoFactor($id)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $user = User::findOrFail($id);

    //     $user->update([
    //         'two_factor_enabled' => true
    //     ]);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Two-factor authentication enabled successfully'
    //     ]);
    // }

    // /**
    //  * @OA\Post(
    //  *     path="/api/users/{id}/two-factor/disable",
    //  *     summary="Disable two-factor authentication",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID of the user",
    //  *         @OA\Schema(type="integer")
    //  *     ),
    //  *     @OA\Response(response=200, description="Two-factor authentication disabled"),
    //  *     @OA\Response(response=404, description="User not found"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function disableTwoFactor($id)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $user = User::findOrFail($id);

    //     $user->update([
    //         'two_factor_enabled' => false
    //     ]);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Two-factor authentication disabled successfully'
    //     ]);
    // }

    // /**
    //  * @OA\Put(
    //  *     path="/api/users/{id}/last-login",
    //  *     summary="Update user last login timestamp",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID of the user",
    //  *         @OA\Schema(type="integer")
    //  *     ),
    //  *     @OA\Response(response=200, description="Last login updated successfully"),
    //  *     @OA\Response(response=404, description="User not found"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function updateLastLogin($id)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $user = User::findOrFail($id);

    //     $user->update([
    //         'last_login' => Carbon::now(),
    //         'is_online' => true
    //     ]);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Last login updated successfully'
    //     ]);
    // }

    // /**
    //  * @OA\Put(
    //  *     path="/api/users/{id}/online-status",
    //  *     summary="Update user online status",
    //  *     tags={"Users"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID of the user",
    //  *         @OA\Schema(type="integer")
    //  *     ),
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\MediaType(
    //  *             mediaType="application/json",
    //  *             @OA\Schema(
    //  *                 type="object",
    //  *                 required={"is_online"},
    //  *                 @OA\Property(property="is_online", type="boolean")
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=200, description="Online status updated successfully"),
    //  *     @OA\Response(response=404, description="User not found"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function updateOnlineStatus(Request $request, $id)
    // {
    //     $authenticatedUser = Auth::user(); // Récupère l'utilisateur connecté via JWT token

    //     $user = User::findOrFail($id);

    //     $validated = $request->validate([
    //         'is_online' => 'required|boolean'
    //     ]);

    //     $user->update($validated);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Online status updated successfully',
    //         'data' => $user
    //     ]);
    // }
}
