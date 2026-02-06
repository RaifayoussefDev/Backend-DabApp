<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class DealerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/dealers",
     *     summary="Get all dealers",
     *     tags={"Dealers"},
     *     @OA\Response(
     *         response=200,
     *         description="List of dealers",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="dealer_title", type="string"),
     *                 @OA\Property(property="dealer_address", type="string"),
     *                 @OA\Property(property="dealer_phone", type="string"),
     *                 @OA\Property(property="latitude", type="number"),
     *                 @OA\Property(property="longitude", type="number"),
     *                 @OA\Property(property="profile_picture", type="string"),
     *                 @OA\Property(property="is_dealer", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $dealers = User::dealers()
            ->select('id', 'first_name', 'last_name', 'dealer_title', 'dealer_address', 'dealer_phone', 'latitude', 'longitude', 'profile_picture', 'email', 'is_dealer')
            ->get();

        return response()->json($dealers);
    }
}
