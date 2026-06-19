<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerGallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer Gallery",
 *     description="Manage trainer image gallery — upload, reorder, and delete gallery images"
 * )
 */
class TrainerGalleryController extends Controller
{
    private const MAX_WIDTH    = 1200;
    private const MAX_HEIGHT   = 900;
    private const QUALITY      = 85;
    private const THUMB_SIZE   = 400;
    private const MAX_IMAGES   = 20;

    // ---------------------------------------------------------------
    // PUBLIC — List gallery for a trainer
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainers/{id}/gallery",
     *     summary="Trainer gallery images",
     *     description="Returns the public gallery images of an approved trainer, ordered by sort_order.",
     *     operationId="getTrainerGallery",
     *     tags={"Trainer Gallery"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Gallery retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",            type="integer", example=5),
     *                     @OA\Property(property="url",           type="string",  example="https://api.dabapp.com/storage/trainers/gallery/img.jpg"),
     *                     @OA\Property(property="thumbnail_url", type="string",  example="https://api.dabapp.com/storage/trainers/gallery/img_thumb.jpg"),
     *                     @OA\Property(property="caption",       type="string",  example="Track day at Al-Naseem"),
     *                     @OA\Property(property="caption_ar",    type="string",  example="يوم الحلبة في النسيم"),
     *                     @OA\Property(property="sort_order",    type="integer", example=0)
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=6)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function index(int $id)
    {
        $trainer = Trainer::approved()->find($id);
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $gallery = TrainerGallery::where('trainer_id', $trainer->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $gallery,
            'count'   => $gallery->count(),
        ]);
    }

    // ---------------------------------------------------------------
    // TRAINER — Upload gallery images
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/trainer/gallery",
     *     summary="Upload gallery images",
     *     description="Upload one or multiple images to the authenticated trainer's gallery. Images are automatically resized and watermarked. Maximum 20 images total per trainer.",
     *     operationId="uploadTrainerGallery",
     *     tags={"Trainer Gallery"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"images[]"},
     *                 @OA\Property(property="images[]",    type="array", @OA\Items(type="string", format="binary"),
     *                     description="Image files (jpg/png/webp, max 5MB each, up to 10 at once)"),
     *                 @OA\Property(property="caption",    type="string", example="Track day at Al-Naseem"),
     *                 @OA\Property(property="caption_ar", type="string", example="يوم الحلبة في النسيم")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Images uploaded",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="3 image(s) added to gallery"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",            type="integer"),
     *                     @OA\Property(property="url",           type="string"),
     *                     @OA\Property(property="thumbnail_url", type="string"),
     *                     @OA\Property(property="caption",       type="string", nullable=true),
     *                     @OA\Property(property="sort_order",    type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Gallery limit reached or no images provided"),
     *     @OA\Response(response=403, description="No trainer profile"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $request->validate([
            'images'      => 'required|array|max:10',
            'images.*'    => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
            'caption'     => 'nullable|string|max:255',
            'caption_ar'  => 'nullable|string|max:255',
        ]);

        $currentCount = TrainerGallery::where('trainer_id', $trainer->id)->count();
        $incoming     = count($request->file('images'));

        if ($currentCount + $incoming > self::MAX_IMAGES) {
            return response()->json([
                'success' => false,
                'message' => "Gallery limit is " . self::MAX_IMAGES . " images. You have {$currentCount} and are trying to add {$incoming}.",
            ], 400);
        }

        $nextSort = TrainerGallery::where('trainer_id', $trainer->id)->max('sort_order') ?? -1;
        $uploaded = [];
        $manager  = new ImageManager(new Driver());

        foreach ($request->file('images') as $file) {
            try {
                $ext      = $file->getClientOriginalExtension();
                $basename = Str::random(24);
                $dir      = 'trainers/gallery';

                // Process main image
                $img = $manager->read($file->getRealPath());
                $img->scaleDown(self::MAX_WIDTH, self::MAX_HEIGHT);
                $encoded = $img->toJpeg(self::QUALITY);

                $mainPath  = "{$dir}/{$basename}.jpg";
                Storage::disk('public')->put($mainPath, (string) $encoded);

                // Thumbnail
                $thumb     = $manager->read($file->getRealPath());
                $thumb->coverDown(self::THUMB_SIZE, self::THUMB_SIZE);
                $thumbEncoded = $thumb->toJpeg(80);

                $thumbPath = "{$dir}/{$basename}_thumb.jpg";
                Storage::disk('public')->put($thumbPath, (string) $thumbEncoded);

                $nextSort++;
                $item = TrainerGallery::create([
                    'trainer_id' => $trainer->id,
                    'path'       => $mainPath,
                    'caption'    => $request->input('caption'),
                    'caption_ar' => $request->input('caption_ar'),
                    'sort_order' => $nextSort,
                ]);

                $uploaded[] = $item;
            } catch (\Exception $e) {
                Log::error('Gallery upload failed', ['trainer_id' => $trainer->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($uploaded) . ' image(s) added to gallery',
            'data'    => $uploaded,
        ], 201);
    }

    // ---------------------------------------------------------------
    // TRAINER — Update caption
    // ---------------------------------------------------------------

    /**
     * @OA\Patch(
     *     path="/api/trainer/gallery/{id}",
     *     summary="Update gallery image caption",
     *     description="Update the caption (EN/AR) of a gallery image.",
     *     operationId="updateTrainerGalleryItem",
     *     tags={"Trainer Gallery"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="caption",    type="string", example="Track day at Al-Naseem"),
     *             @OA\Property(property="caption_ar", type="string", example="يوم الحلبة في النسيم")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Caption updated"),
     *     @OA\Response(response=403, description="Not your image"),
     *     @OA\Response(response=404, description="Image not found")
     * )
     */
    public function update(Request $request, int $id)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();
        $item    = TrainerGallery::find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Gallery image not found'], 404);
        }
        if (!$trainer || $item->trainer_id !== $trainer->id) {
            return response()->json(['success' => false, 'message' => 'Not your image'], 403);
        }

        $validated = $request->validate([
            'caption'    => 'nullable|string|max:255',
            'caption_ar' => 'nullable|string|max:255',
        ]);

        $item->update($validated);

        return response()->json(['success' => true, 'message' => 'Caption updated', 'data' => $item]);
    }

    // ---------------------------------------------------------------
    // TRAINER — Delete gallery image
    // ---------------------------------------------------------------

    /**
     * @OA\Delete(
     *     path="/api/trainer/gallery/{id}",
     *     summary="Delete a gallery image",
     *     description="Permanently deletes a gallery image and its thumbnail from storage.",
     *     operationId="deleteTrainerGalleryItem",
     *     tags={"Trainer Gallery"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=5)),
     *     @OA\Response(response=200, description="Image deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Gallery image deleted")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not your image"),
     *     @OA\Response(response=404, description="Image not found")
     * )
     */
    public function destroy(int $id)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();
        $item    = TrainerGallery::find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Gallery image not found'], 404);
        }
        if (!$trainer || $item->trainer_id !== $trainer->id) {
            return response()->json(['success' => false, 'message' => 'Not your image'], 403);
        }

        // Delete files from storage
        Storage::disk('public')->delete($item->path);
        $info      = pathinfo($item->path);
        $thumbPath = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
        Storage::disk('public')->delete($thumbPath);

        $item->delete();

        return response()->json(['success' => true, 'message' => 'Gallery image deleted']);
    }

    // ---------------------------------------------------------------
    // TRAINER — Reorder gallery
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/trainer/gallery/reorder",
     *     summary="Reorder gallery images",
     *     description="Set the display order of gallery images. Send an ordered array of image IDs.",
     *     operationId="reorderTrainerGallery",
     *     tags={"Trainer Gallery"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order"},
     *             @OA\Property(property="order", type="array",
     *                 @OA\Items(type="integer"),
     *                 example={5, 3, 7, 1},
     *                 description="Array of gallery image IDs in the desired display order"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Order updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Gallery order updated")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No trainer profile"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function reorder(Request $request)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:trainer_gallery,id',
        ]);

        foreach ($request->order as $index => $imageId) {
            TrainerGallery::where('id', $imageId)
                ->where('trainer_id', $trainer->id)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true, 'message' => 'Gallery order updated']);
    }

    // ---------------------------------------------------------------
    // ADMIN — Delete any gallery image
    // ---------------------------------------------------------------

    /**
     * @OA\Delete(
     *     path="/api/admin/trainer-gallery/{id}",
     *     summary="Admin: delete gallery image",
     *     description="Admin can delete any trainer gallery image.",
     *     operationId="adminDeleteTrainerGalleryItem",
     *     tags={"Admin — Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function adminDestroy(int $id)
    {
        $item = TrainerGallery::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Gallery image not found'], 404);
        }

        Storage::disk('public')->delete($item->path);
        $info      = pathinfo($item->path);
        $thumbPath = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
        Storage::disk('public')->delete($thumbPath);

        $item->delete();

        return response()->json(['success' => true, 'message' => 'Gallery image deleted by admin']);
    }
}
