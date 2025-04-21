<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index()
    {
        $users = User::all();
        return response()->json($users);
    }

    /**
     * Store a newly created user in storage.
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
            'postal_code'=> 'nullable|string',
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
     * Display the specified user.
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    /**
     * Update the specified user in storage.
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
            'postal_code'=> 'nullable|string',
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
     * Remove the specified user from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete(); // uses softDeletes
        return response()->json(['message' => 'User deleted successfully']);
    }
}
