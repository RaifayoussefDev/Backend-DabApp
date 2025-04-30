<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Wishlist",
 *     description="Operations related to wishlists"
 * )
 */
class WishlistController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/wishlists",
     *     tags={"Wishlist"},
     *     summary="Get all wishlists",
     *     @OA\Response(response=200, description="List of wishlists")
     * )
     */
    public function index()
    {
        return Wishlist::with(['user', 'listing'])->get();
    }

    /**
     * @OA\Post(
     *     path="/api/wishlists",
     *     tags={"Wishlist"},
     *     summary="Add listing to wishlist (auth required)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"listing_id"},
     *             @OA\Property(property="listing_id", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Added to wishlist"),
     *     @OA\Response(response=409, description="Already in wishlist"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'listing_id' => 'required|exists:listings,id',
        ]);

        $user = Auth::user(); // Récupère l'utilisateur connecté via token

        // Vérifie s'il existe déjà un wishlist pour ce user + listing
        $exists = Wishlist::where('user_id', $user->id)
            ->where('listing_id', $request->listing_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already in wishlist'], 409);
        }

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'listing_id' => $request->listing_id
        ]);

        return response()->json($wishlist, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/wishlists/{id}",
     *     tags={"Wishlist"},
     *     summary="Get a wishlist by ID",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Wishlist details"),
     *     @OA\Response(response=404, description="Wishlist not found")
     * )
     */
    public function show($id)
    {
        $wishlist = Wishlist::with(['user', 'listing'])->find($id);
        if (!$wishlist) return response()->json(['message' => 'Not found'], 404);
        return $wishlist;
    }

    /**
     * @OA\Put(
     *     path="/api/wishlists/{id}",
     *     tags={"Wishlist"},
     *     summary="Update a wishlist",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="listing_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Wishlist updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $wishlist = Wishlist::findOrFail($id);
        $wishlist->update($request->only('user_id', 'listing_id'));
        return $wishlist;
    }

    /**
     * @OA\Delete(
     *     path="/api/wishlists/{listing_id}",
     *     tags={"Wishlist"},
     *     summary="Remove listing from wishlist (auth required)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="listing_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Removed from wishlist"),
     *     @OA\Response(response=404, description="Wishlist item not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */

    public function destroy($listing_id)
    {
        $user = Auth::user();

        $wishlist = Wishlist::where('user_id', $user->id)
            ->where('listing_id', $listing_id)
            ->first();

        if (!$wishlist) {
            return response()->json(['message' => 'Wishlist item not found'], 404);
        }

        $wishlist->delete();

        return response()->json(['message' => 'Removed from wishlist'], 200);
    }
}
