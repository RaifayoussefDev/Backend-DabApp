<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerLike;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer Likes",
 *     description="Like or unlike a trainer profile"
 * )
 */
class TrainerLikeController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/trainers/{id}/like",
     *     summary="Like / Unlike a trainer",
     *     description="Toggles the like on a trainer's profile. Calling it again removes the like. Returns the updated like count and the current like status.",
     *     operationId="toggleTrainerLike",
     *     tags={"Trainer Likes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Like toggled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",     type="boolean", example=true),
     *             @OA\Property(property="liked",       type="boolean", example=true,
     *                 description="true = just liked, false = just unliked"),
     *             @OA\Property(property="likes_count", type="integer", example=36),
     *             @OA\Property(property="message",     type="string",  example="Trainer liked")
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

        $existing = TrainerLike::where('trainer_id', $trainer->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $trainer->decrement('likes_count');
            $liked   = false;
            $message = 'Trainer unliked';
        } else {
            TrainerLike::create(['trainer_id' => $trainer->id, 'user_id' => $user->id]);
            $trainer->increment('likes_count');
            $liked   = true;
            $message = 'Trainer liked';
        }

        return response()->json([
            'success'     => true,
            'liked'       => $liked,
            'likes_count' => $trainer->fresh()->likes_count,
            'message'     => $message,
        ]);
    }
}
