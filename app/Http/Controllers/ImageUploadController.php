<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageUploadController extends Controller
{

    /**
 * @OA\Post(
 *     path="/api/upload",
 *     summary="Upload multiple images",
 *     description="Allows users to upload multiple images to the server.",
 *     operationId="uploadImages",
 *     tags={"Image Upload"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"images[]"},
 *                 @OA\Property(
 *                     property="images[]",
 *                     type="array",
 *                     @OA\Items(
 *                         type="string",
 *                         format="binary"
 *                     ),
 *                     description="Multiple image files to upload"
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Images uploaded successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Images uploaded successfully"),
 *             @OA\Property(
 *                 property="paths",
 *                 type="array",
 *                 @OA\Items(type="string", example="http://yourdomain.com/storage/listings/image1.jpg")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Bad Request",
 *         @OA\JsonContent(
 *             @OA\Property(property="error", type="string", example="No images found in request.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal Server Error",
 *         @OA\JsonContent(
 *             @OA\Property(property="error", type="string", example="An error occurred while uploading images.")
 *         )
 *     )
 * )
 */

    public function upload(Request $request)
    {
        Log::info('FILES:', $request->allFiles());

        if (!$request->hasFile('images')) {
            return response()->json(['error' => 'No images found in request.'], 400);
        }

        $paths = [];

        foreach ($request->file('images') as $image) {
            $path = $image->store('listings', 'public');
            $paths[] = asset('storage/' . $path);
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'paths' => $paths,
        ]);
    }
}
