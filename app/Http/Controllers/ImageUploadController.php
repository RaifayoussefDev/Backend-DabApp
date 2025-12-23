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

    // Configuration du watermark
    private const WATERMARK_PATH = 'watermark/logo.png'; // Chemin relatif dans storage/app/public
    private const WATERMARK_OPACITY = 100; // Opacité du watermark (0-100)
    private const WATERMARK_POSITION = 'bottom-right'; // Position: bottom-right, bottom-left, top-right, top-left, center
    private const WATERMARK_PADDING = 20; // Padding depuis les bords en pixels
    private const WATERMARK_MAX_WIDTH_PERCENT = 20; // Taille max du watermark en % de la largeur de l'image

    // Configuration des icônes
    private const ICON_SIZE = 16; // Taille des icônes en pixels

    /**
     * @OA\Post(
     *     path="/api/upload-image",
     *     summary="Upload multiple images",
     *     description="Allows users to upload multiple images to the server with automatic resizing and watermark.",
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
     *                     description="Multiple image files to upload (will be automatically resized and watermarked)"
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

                // Nom de fichier aléatoire
                $filename = Str::random(20) . '.' . $uploadedFile->getClientOriginalExtension();

                // Redimensionner l'image
                $processedImage = $this->processImage($uploadedFile);

                // Ajouter le watermark
                $processedImage = $this->addWatermark($processedImage);

                // Sauvegarder dans listings/
                $imagePath = $this->saveImage($processedImage, "listings/{$filename}");

                // Ajouter le chemin public
                $paths[] = asset('storage/' . $imagePath);

                Log::info("Image saved successfully", [
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
     * @OA\Post(
     *     path="/api/upload-image-no-watermark",
     *     summary="Upload multiple images without watermark",
     *     description="Allows users to upload multiple images to the server with automatic resizing but WITHOUT watermark.",
     *     operationId="uploadImagesNoWatermark",
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
     *                     description="Multiple image files to upload (will be automatically resized but NOT watermarked)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Images uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Images uploaded successfully"),
     *             @OA\Property(property="paths", type="array", @OA\Items(type="string", example="http://yourdomain.com/storage/listings/random_name.jpg"))
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=422, description="Validation Error"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function uploadNoWatermark(Request $request)
    {
        try {
            $request->validate([
                'images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240'
            ]);

            if (!$request->hasFile('images')) {
                return response()->json(['error' => 'No images found in request.'], 400);
            }

            $paths = [];
            foreach ($request->file('images') as $uploadedFile) {
                $filename = Str::random(20) . '.' . $uploadedFile->getClientOriginalExtension();
                $processedImage = $this->processImage($uploadedFile);
                $imagePath = $this->saveImage($processedImage, "listings/{$filename}");
                $paths[] = asset('storage/' . $imagePath);
            }

            return response()->json([
                'message' => 'Images uploaded successfully without watermark',
                'paths' => $paths
            ]);
        } catch (\Exception $e) {
            Log::error('Image upload (no watermark) failed: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while uploading images.'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/upload-icon",
     *     summary="Upload an icon (16x16)",
     *     description="Allows users to upload an icon that will be automatically resized to 16x16 pixels",
     *     operationId="uploadIcon",
     *     tags={"Image Upload"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"icon"},
     *                 @OA\Property(
     *                     property="icon",
     *                     type="string",
     *                     format="binary",
     *                     description="Icon file to upload (will be resized to 16x16)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Icon uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Icon uploaded successfully"),
     *             @OA\Property(property="path", type="string", example="http://yourdomain.com/storage/icons/icon_abc123.png")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No icon found in request.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid icon format.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="An error occurred while uploading icon.")
     *         )
     *     )
     * )
     */
    public function uploadIcon(Request $request)
    {
        try {
            Log::info('Icon upload request received');

            $request->validate([
                'icon' => 'required|image|mimes:jpeg,jpg,png,webp,ico|max:2048'
            ]);

            if (!$request->hasFile('icon')) {
                return response()->json(['error' => 'No icon found in request.'], 400);
            }

            $uploadedFile = $request->file('icon');

            Log::info("Processing icon", [
                'original_name' => $uploadedFile->getClientOriginalName(),
                'size' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getMimeType()
            ]);

            // Nom de fichier aléatoire
            $filename = 'icon_' . Str::random(15) . '.png'; // Toujours sauvegarder en PNG pour préserver la transparence

            // Redimensionner l'icône à 16x16
            $processedIcon = $this->processIcon($uploadedFile);

            // Sauvegarder dans icons/
            $iconPath = $this->saveImage($processedIcon, "icons/{$filename}");

            // Ajouter le chemin public
            $publicPath = asset('storage/' . $iconPath);

            Log::info("Icon saved successfully", [
                'filename' => $filename,
                'path' => $iconPath
            ]);

            return response()->json([
                'message' => 'Icon uploaded successfully',
                'path' => $publicPath
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Icon validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Icon upload failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'An error occurred while uploading icon.',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Ajoute un watermark à l'image
     */
    private function addWatermark($image)
    {
        try {
            // Vérifier si le fichier watermark existe
            $watermarkPath = storage_path('app/public/' . self::WATERMARK_PATH);

            if (!file_exists($watermarkPath)) {
                Log::warning('Watermark file not found', ['path' => $watermarkPath]);
                return $image; // Retourner l'image sans watermark
            }

            // Charger le watermark
            $manager = new ImageManager(new Driver());
            $watermark = $manager->read($watermarkPath);

            // Calculer la taille du watermark (proportionnelle à l'image)
            $maxWatermarkWidth = ($image->width() * self::WATERMARK_MAX_WIDTH_PERCENT) / 100;
            $maxWatermarkHeight = ($image->height() * self::WATERMARK_MAX_WIDTH_PERCENT) / 100;

            // Redimensionner le watermark pour qu'il soit toujours à la bonne taille
            $ratio = min($maxWatermarkWidth / $watermark->width(), $maxWatermarkHeight / $watermark->height());
            $newWidth = (int)($watermark->width() * $ratio);
            $newHeight = (int)($watermark->height() * $ratio);
            $watermark->resize($newWidth, $newHeight);

            Log::info('Watermark resized', [
                'original_width' => $watermark->width(),
                'original_height' => $watermark->height(),
                'new_width' => $newWidth,
                'new_height' => $newHeight,
                'image_width' => $image->width(),
                'image_height' => $image->height()
            ]);

            // Calculer la position du watermark
            $position = $this->calculateWatermarkPosition(
                $image->width(),
                $image->height(),
                $watermark->width(),
                $watermark->height()
            );

            // Placer le watermark sur l'image avec opacité
            $image->place(
                $watermark,
                $position['position'],
                $position['x'],
                $position['y'],
                self::WATERMARK_OPACITY
            );

            Log::info('Watermark applied successfully');

            return $image;

        } catch (\Exception $e) {
            Log::error('Failed to apply watermark', [
                'message' => $e->getMessage()
            ]);
            // En cas d'erreur, retourner l'image sans watermark
            return $image;
        }
    }

    /**
     * Calcule la position du watermark
     */
    private function calculateWatermarkPosition($imageWidth, $imageHeight, $watermarkWidth, $watermarkHeight)
    {
        $padding = self::WATERMARK_PADDING;

        switch (self::WATERMARK_POSITION) {
            case 'top-left':
                return [
                    'position' => 'top-left',
                    'x' => $padding,
                    'y' => $padding
                ];

            case 'top-right':
                return [
                    'position' => 'top-right',
                    'x' => $padding,
                    'y' => $padding
                ];

            case 'bottom-left':
                return [
                    'position' => 'bottom-left',
                    'x' => $padding,
                    'y' => $padding
                ];

            case 'bottom-right':
                return [
                    'position' => 'bottom-right',
                    'x' => $padding,
                    'y' => $padding
                ];

            case 'center':
                return [
                    'position' => 'center',
                    'x' => 0,
                    'y' => 0
                ];

            default:
                return [
                    'position' => 'bottom-right',
                    'x' => $padding,
                    'y' => $padding
                ];
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
     * Traite et redimensionne l'icône à 16x16
     */
    private function processIcon($uploadedFile)
    {
        // Créer le manager avec le driver GD
        $manager = new ImageManager(new Driver());
        $icon = $manager->read($uploadedFile);

        // Obtenir les dimensions originales
        $originalWidth = $icon->width();
        $originalHeight = $icon->height();

        Log::info('Original icon dimensions', [
            'width' => $originalWidth,
            'height' => $originalHeight
        ]);

        // Redimensionner à 16x16 en gardant le ratio et en ajoutant un crop si nécessaire
        $icon = $icon->cover(self::ICON_SIZE, self::ICON_SIZE);

        Log::info('Icon resized', [
            'new_width' => $icon->width(),
            'new_height' => $icon->height()
        ]);

        return $icon;
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
     *     path="/api/delete-image/{filename}",
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

    /**
     * @OA\Delete(
     *     path="/api/delete-icon/{filename}",
     *     summary="Delete an uploaded icon",
     *     description="Delete an uploaded icon",
     *     operationId="deleteIcon",
     *     tags={"Image Upload"},
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="The filename of the icon to delete"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Icon deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Icon deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Icon not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Icon not found")
     *         )
     *     )
     * )
     */
    public function deleteIcon(string $filename)
    {
        try {
            $iconPath = "icons/{$filename}";

            if (!Storage::disk('public')->exists($iconPath)) {
                return response()->json(['error' => 'Icon not found'], 404);
            }

            Storage::disk('public')->delete($iconPath);

            Log::info('Icon deleted successfully', ['filename' => $filename]);

            return response()->json(['message' => 'Icon deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Icon deletion failed', [
                'filename' => $filename,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'An error occurred while deleting the icon.'
            ], 500);
        }
    }
}
