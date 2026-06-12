<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerComment;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer Comments",
 *     description="Comment on trainer profiles with nested reply support"
 * )
 */
class TrainerCommentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/trainers/{id}/comments",
     *     summary="Trainer comments",
     *     description="Returns approved comments on a trainer's profile, including nested replies.",
     *     operationId="getTrainerComments",
     *     tags={"Trainer Comments"},
     *     @OA\Parameter(name="id",       in="path",  required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Response(
     *         response=200,
     *         description="Comments retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",         type="integer", example=1),
     *                         @OA\Property(property="content",    type="string",  example="Great trainer, highly recommended!"),
     *                         @OA\Property(property="created_at", type="string",  format="datetime"),
     *                         @OA\Property(property="user", type="object",
     *                             @OA\Property(property="id",         type="integer"),
     *                             @OA\Property(property="first_name", type="string"),
     *                             @OA\Property(property="last_name",  type="string"),
     *                             @OA\Property(property="avatar",     type="string", nullable=true)
     *                         ),
     *                         @OA\Property(property="replies", type="array",
     *                             @OA\Items(type="object",
     *                                 @OA\Property(property="id",         type="integer"),
     *                                 @OA\Property(property="content",    type="string"),
     *                                 @OA\Property(property="created_at", type="string", format="datetime"),
     *                                 @OA\Property(property="user",       type="object")
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function index(Request $request, int $id)
    {
        $trainer = Trainer::approved()->find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $comments = TrainerComment::where('trainer_id', $trainer->id)
            ->rootOnly()
            ->approved()
            ->with([
                'user:id,first_name,last_name,avatar',
                'replies.user:id,first_name,last_name,avatar',
            ])
            ->latest()
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data'    => $comments,
            'message' => 'Comments retrieved successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/trainers/{id}/comments",
     *     summary="Add a comment",
     *     description="Post a comment on a trainer's profile. Optionally reply to an existing comment using parent_id. Comments go through moderation before appearing publicly.",
     *     operationId="addTrainerComment",
     *     tags={"Trainer Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content",   type="string",  example="Best trainer I've worked with! Very professional."),
     *             @OA\Property(property="parent_id", type="integer", nullable=true, example=null,
     *                 description="ID of a root comment to reply to. Leave null for a top-level comment.")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Comment submitted — pending moderation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Comment submitted and pending moderation")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found"),
     *     @OA\Response(response=422, description="Validation error — invalid parent_id")
     * )
     */
    public function store(Request $request, int $id)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::approved()->find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $validated = $request->validate([
            'content'   => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:trainer_comments,id',
        ]);

        // Ensure parent belongs to the same trainer
        if (!empty($validated['parent_id'])) {
            $parent = TrainerComment::find($validated['parent_id']);
            if ($parent->trainer_id !== $trainer->id) {
                return response()->json(['success' => false, 'message' => 'Invalid parent comment'], 422);
            }
        }

        TrainerComment::create([
            'trainer_id'  => $trainer->id,
            'user_id'     => $user->id,
            'parent_id'   => $validated['parent_id'] ?? null,
            'content'     => $validated['content'],
            'is_approved' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment submitted and pending moderation',
        ], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/trainer/comments/{commentId}",
     *     summary="Delete a comment",
     *     description="Delete your own comment. Only the comment owner can delete.",
     *     operationId="deleteTrainerComment",
     *     tags={"Trainer Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="commentId", in="path", required=true, @OA\Schema(type="integer", example=5)),
     *     @OA\Response(response=200, description="Comment deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Comment deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not your comment"),
     *     @OA\Response(response=404, description="Comment not found")
     * )
     */
    public function destroy(int $commentId)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $comment = TrainerComment::find($commentId);

        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }

        if ($comment->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Not your comment'], 403);
        }

        $comment->delete();

        return response()->json(['success' => true, 'message' => 'Comment deleted successfully']);
    }
}
