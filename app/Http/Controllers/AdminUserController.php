<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/users/autocomplete",
     *     summary="Search users by name or phone",
     *     tags={"Admin Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         required=true,
     *         description="Search string (Phone, First Name, or Last Name)",
     *         @OA\Schema(type="string", example="John")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users found",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="first_name", type="string"),
     *                 @OA\Property(property="last_name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="full_name", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function autocomplete(Request $request)
    {
        $search = $request->input('query');

        if (empty($search)) {
            return response()->json([]);
        }

        $users = User::where(function($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            })
            ->select('id', 'first_name', 'last_name', 'email', 'phone')
            ->limit(10)
            ->get();

        // Append full_name attribute
        $users->transform(function ($user) {
            $user->full_name = $user->first_name . ' ' . $user->last_name;
            return $user;
        });

        return response()->json($users);
    }
}
