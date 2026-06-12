<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerFavorite;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer Favorites",
 *     description="Save and manage favorite trainers"
 * )
 */
class TrainerFavoriteController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/trainers/{id}/favorite",
     *     summary="Add / Remove from favorites",
     *     description="Toggles the trainer in the authenticated user's favorites list. Calling it again removes it.",
     *     operationId="toggleTrainerFavorite",
     *     tags={"Trainer Favorites"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Favorite toggled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",      type="boolean", example=true),
     *             @OA\Property(property="favorited",    type="boolean", example=true,
     *                 description="true = added to favorites, false = removed from favorites"),
     *             @OA\Property(property="message",      type="string",  example="Trainer added to favorites")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function toggle(int $id)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::approved()->find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $existing = TrainerFavorite::where('trainer_id', $trainer->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['success' => true, 'favorited' => false, 'message' => 'Trainer removed from favorites']);
        }

        TrainerFavorite::create(['trainer_id' => $trainer->id, 'user_id' => $user->id]);

        return response()->json(['success' => true, 'favorited' => true, 'message' => 'Trainer added to favorites']);
    }

    /**
     * @OA\Get(
     *     path="/api/user/trainer-favorites",
     *     summary="My favorite trainers",
     *     description="Returns the authenticated user's list of favorited trainers.",
     *     operationId="myTrainerFavorites",
     *     tags={"Trainer Favorites"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Response(
     *         response=200,
     *         description="Favorites retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer", example=1),
     *                         @OA\Property(property="name",           type="string",  example="Khalid Al-Mansouri"),
     *                         @OA\Property(property="specialty",      type="string",  example="coaching"),
     *                         @OA\Property(property="rating_average", type="number",  format="float", example=4.8),
     *                         @OA\Property(property="price_per_hour", type="number",  format="float", example=150.00),
     *                         @OA\Property(property="photo_url",      type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myFavorites(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $favorites = TrainerFavorite::where('user_id', $user->id)
            ->with(['trainer' => fn ($q) => $q->approved()->select('id', 'name', 'name_ar', 'specialty', 'rating_average', 'price_per_hour', 'photo', 'likes_count', 'is_available')])
            ->latest()
            ->paginate($request->get('per_page', 10));

        $favorites->getCollection()->transform(fn ($f) => array_merge(
            $f->trainer ? $f->trainer->append('photo_url')->toArray() : [],
            ['favorited_at' => $f->created_at]
        ));

        return response()->json([
            'success' => true,
            'data'    => $favorites,
            'message' => 'Favorite trainers retrieved successfully',
        ]);
    }
}
