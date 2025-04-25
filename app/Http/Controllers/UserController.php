<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="Get list of users",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successfully retrieved list of users"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function index()
    {
        $users = User::all();
        return response()->json($users);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     summary="Create a new user",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"first_name", "last_name", "email", "role_id"},
     *                 @OA\Property(property="first_name", type="string"),
     *                 @OA\Property(property="last_name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="birthday", type="string", format="date"),
     *                 @OA\Property(property="gender", type="string"),
     *                 @OA\Property(property="profile_picture", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="postal_code", type="string"),
     *                 @OA\Property(property="role_id", type="integer"),
     *                 @OA\Property(property="country_id", type="integer"),
     *                 @OA\Property(property="language", type="string"),
     *                 @OA\Property(property="timezone", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="User created successfully"),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'nullable|string',
            'birthday'   => 'nullable|date',
            'gender'     => 'nullable|string',
            'profile_picture' => 'nullable|string',
            'address'    => 'nullable|string',
            'postal_code' => 'nullable|string',
            'role_id'    => 'required|exists:roles,id',
            'country_id' => 'nullable|exists:countries,id',
            'language'   => 'nullable|string',
            'timezone'   => 'nullable|string',
        ]);

        // Auto-generate and hash password
        $generatedPassword = Str::random(10);
        $validated['password'] = Hash::make($generatedPassword);

        $user = User::create($validated);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
            'plain_password' => $generatedPassword // optionally return it for emailing or debugging
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Get user by ID",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Successfully retrieved user"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     summary="Update user details",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="first_name", type="string"),
     *                 @OA\Property(property="last_name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="birthday", type="string", format="date"),
     *                 @OA\Property(property="gender", type="string"),
     *                 @OA\Property(property="profile_picture", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="postal_code", type="string"),
     *                 @OA\Property(property="role_id", type="integer"),
     *                 @OA\Property(property="country_id", type="integer"),
     *                 @OA\Property(property="language", type="string"),
     *                 @OA\Property(property="timezone", type="string"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="is_online", type="boolean")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="User updated successfully"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name'  => 'sometimes|string|max:255',
            'email'      => 'sometimes|email|unique:users,email,' . $user->id,
            'phone'      => 'nullable|string',
            'birthday'   => 'nullable|date',
            'gender'     => 'nullable|string',
            'profile_picture' => 'nullable|string',
            'address'    => 'nullable|string',
            'postal_code' => 'nullable|string',
            'verified'   => 'nullable|boolean',
            'is_active'  => 'nullable|boolean',
            'is_online'  => 'nullable|boolean',
            'last_login' => 'nullable|date',
            'token'      => 'nullable|string',
            'token_expiration' => 'nullable|date',
            'role_id'    => 'nullable|exists:roles,id',
            'country_id' => 'nullable|exists:countries,id',
            'language'   => 'nullable|string',
            'timezone'   => 'nullable|string',
            'two_factor_enabled' => 'nullable|boolean'
        ]);

        // Do NOT update password
        unset($validated['password']);

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     summary="Delete a user",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="User deleted successfully"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete(); // uses softDeletes
        return response()->json(['message' => 'User deleted successfully']);
    }
}
