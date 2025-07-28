<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    // Configuration des tailles d'images
    private const MAX_WIDTH = 1200;
    private const MAX_HEIGHT = 800;
    private const QUALITY = 85;
    private const THUMBNAIL_SIZE = 300;

    /**
     * @OA\Post(
     *     path="/api/upload",
     *     summary="Upload multiple images",
     *     description="Allows users to upload multiple images to the server with automatic resizing.",
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
     *                     description="Multiple image files to upload (will be automatically resized)"
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
     *                 property="images",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="original", type="string", example="http://yourdomain.com/storage/listings/image1.jpg"),
     *                     @OA\Property(property="thumbnail", type="string", example="http://yourdomain.com/storage/listings/thumbnails/image1_thumb.jpg")
     *                 )
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
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid image format.")
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
    try {
        Log::info('Upload request received', ['files_count' => count($request->allFiles())]);

        $request->validate([
            'images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240'
        ]);

        if (!$request->hasFile('images')) {
            return response()->json(['error' => 'No images found in request.'], 400);
        }

        $paths = [];

        foreach ($request->file('images') as $index => $uploadedFile) {
            Log::info("Processing image {$index}", [
                'original_name' => $uploadedFile->getClientOriginalName(),
                'size' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getMimeType()
            ]);

            // Générer un nom de fichier complètement aléatoire
            $filename = Str::random(20) . '.' . $uploadedFile->getClientOriginalExtension();

            // Traitement et sauvegarde de l'image principale
            $processedImage = $this->processImage($uploadedFile);
            $imagePath = $this->saveImage($processedImage, "listings/{$filename}");

            // Création de la miniature (optionnel)
            $thumbnail = $this->createThumbnail($uploadedFile);
            $this->saveImage($thumbnail, "listings/thumbnails/thumb_{$filename}");

            $paths[] = asset('storage/' . $imagePath);

            Log::info("Image processed successfully", [
                'filename' => $filename,
                'path' => $imagePath
            ]);
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'paths' => $paths
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::warning('Validation failed', ['errors' => $e->errors()]);
        return response()->json([
            'error' => 'Validation failed',
            'details' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        Log::error('Image upload failed', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'error' => 'An error occurred while uploading images.',
            'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
}


    /**
     * Traite et redimensionne l'image principale
     */
    private function processImage($uploadedFile)
    {
        // Créer le manager avec le driver GD
        $manager = new ImageManager(new Driver());
        $image = $manager->read($uploadedFile);
        
        // Obtenir les dimensions originales
        $originalWidth = $image->width();
        $originalHeight = $image->height();
        
        Log::info('Original image dimensions', [
            'width' => $originalWidth,
            'height' => $originalHeight
        ]);

        // Redimensionner seulement si nécessaire
        if ($originalWidth > self::MAX_WIDTH || $originalHeight > self::MAX_HEIGHT) {
            // Calculer les nouvelles dimensions en gardant le ratio
            $ratio = min(self::MAX_WIDTH / $originalWidth, self::MAX_HEIGHT / $originalHeight);
            $newWidth = (int)($originalWidth * $ratio);
            $newHeight = (int)($originalHeight * $ratio);
            
            $image = $image->resize($newWidth, $newHeight);
            
            Log::info('Image resized', [
                'new_width' => $image->width(),
                'new_height' => $image->height()
            ]);
        }

        return $image;
    }

    /**
     * Crée une miniature de l'image
     */
    private function createThumbnail($uploadedFile)
    {
        $manager = new ImageManager(new Driver());
        $thumbnail = $manager->read($uploadedFile);
        
        // Créer une miniature carrée avec crop intelligent
        $thumbnail = $thumbnail->cover(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE);

        return $thumbnail;
    }

    /**
     * Génère un nom de fichier unique
     */
    private function generateUniqueFilename($uploadedFile): string
    {
        $extension = $uploadedFile->getClientOriginalExtension();
        $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $cleanName = Str::slug($originalName);
        
        return $cleanName . '_' . time() . '_' . Str::random(8) . '.' . $extension;
    }

    /**
     * Sauvegarde l'image traitée
     */
    private function saveImage($image, string $path): string
    {
        $fullPath = storage_path('app/public/' . $path);
        
        // Créer le dossier s'il n'existe pas
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Sauvegarder l'image avec qualité optimisée
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($extension), ['jpg', 'jpeg'])) {
            $image->toJpeg(self::QUALITY)->save($fullPath);
        } elseif (strtolower($extension) === 'png') {
            $image->toPng()->save($fullPath);
        } elseif (strtolower($extension) === 'webp') {
            $image->toWebp(self::QUALITY)->save($fullPath);
        } else {
            $image->save($fullPath);
        }
        
        return $path;
    }

    /**
     * @OA\Delete(
     *     path="/api/upload/{filename}",
     *     summary="Delete an uploaded image",
     *     description="Delete an uploaded image and its thumbnail",
     *     operationId="deleteImage",
     *     tags={"Image Upload"},
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="The filename of the image to delete"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Image deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Image not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Image not found")
     *         )
     *     )
     * )
     */
    public function delete(string $filename)
    {
        try {
            $imagePath = "listings/{$filename}";
            $thumbnailPath = "listings/thumbnails/thumb_{$filename}";

            $deleted = false;

            if (Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
                $deleted = true;
            }

            if (Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
                $deleted = true;
            }

            if (!$deleted) {
                return response()->json(['error' => 'Image not found'], 404);
            }

            Log::info('Image deleted successfully', ['filename' => $filename]);

            return response()->json(['message' => 'Image deleted successfully']);

        } catch (\Exception $e) {
            Log::error('Image deletion failed', [
                'filename' => $filename,
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'An error occurred while deleting the image.'
            ], 500);
        }
    }
}