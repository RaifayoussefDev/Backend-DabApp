<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\GuideImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Guide Images",
 *     description="API Endpoints pour la gestion des images de guides"
 * )
 */
class GuideImageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/guides/{guide_id}/images",
     *     summary="Liste toutes les images d'un guide",
     *     tags={"Guide Images"},
     *     @OA\Parameter(name="guide_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Liste des images")
     * )
     */
    public function index($guide_id)
    {
        $guide = Guide::find($guide_id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide non trouvé'
            ], 404);
        }

        $images = $guide->images()->orderBy('order_position')->get()->map(function ($image) {
            return [
                'id' => $image->id,
                'image_url' => $image->image_url,
                'caption' => $image->caption,
                'order_position' => $image->order_position,
                'created_at' => $image->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($images);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/{guide_id}/images",
     *     summary="Ajouter une image à un guide",
     *     tags={"Guide Images"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="guide_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=201, description="Image ajoutée")
     * )
     */
    public function store(Request $request, $guide_id)
    {
        $guide = Guide::find($guide_id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide non trouvé'
            ], 404);
        }

        // Vérifier autorisation (auteur ou admin role_id = 1)
        $user = Auth::user();
        if ($guide->author_id !== $user->id && $user->role_id != 1) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'image_url' => 'required|string|max:255',
            'caption' => 'nullable|string',
            'order_position' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Si order_position n'est pas fourni, mettre à la fin
        $order = $request->order_position ?? $guide->images()->max('order_position') + 1;

        $image = GuideImage::create([
            'guide_id' => $guide_id,
            'image_url' => $request->image_url,
            'caption' => $request->caption,
            'order_position' => $order,
        ]);

        return response()->json([
            'message' => 'Image ajoutée avec succès',
            'data' => [
                'id' => $image->id,
                'image_url' => $image->image_url,
                'caption' => $image->caption,
                'order_position' => $image->order_position,
                'created_at' => $image->created_at->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/guides/{guide_id}/images/{id}",
     *     summary="Mettre à jour une image",
     *     tags={"Guide Images"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="guide_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Image mise à jour")
     * )
     */
    public function update(Request $request, $guide_id, $id)
    {
        $guide = Guide::find($guide_id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide non trouvé'
            ], 404);
        }

        // Vérifier autorisation (auteur ou admin role_id = 1)
        $user = Auth::user();
        if ($guide->author_id !== $user->id && $user->role_id != 1) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        $image = GuideImage::where('guide_id', $guide_id)->find($id);

        if (!$image) {
            return response()->json([
                'message' => 'Image non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'image_url' => 'sometimes|string|max:255',
            'caption' => 'nullable|string',
            'order_position' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $image->update($request->only(['image_url', 'caption', 'order_position']));

        return response()->json([
            'message' => 'Image mise à jour avec succès',
            'data' => [
                'id' => $image->id,
                'image_url' => $image->image_url,
                'caption' => $image->caption,
                'order_position' => $image->order_position,
                'updated_at' => $image->updated_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/guides/{guide_id}/images/{id}",
     *     summary="Supprimer une image",
     *     tags={"Guide Images"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="guide_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Image supprimée")
     * )
     */
    public function destroy($guide_id, $id)
    {
        $guide = Guide::find($guide_id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide non trouvé'
            ], 404);
        }

        // Vérifier autorisation (auteur ou admin role_id = 1)
        $user = Auth::user();
        if ($guide->author_id !== $user->id && $user->role_id != 1) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        $image = GuideImage::where('guide_id', $guide_id)->find($id);

        if (!$image) {
            return response()->json([
                'message' => 'Image non trouvée'
            ], 404);
        }

        $image->delete();

        return response()->json([
            'message' => 'Image supprimée avec succès'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/{guide_id}/images/reorder",
     *     summary="Réorganiser l'ordre des images",
     *     tags={"Guide Images"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="guide_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Ordre mis à jour")
     * )
     */
    public function reorder(Request $request, $guide_id)
    {
        $guide = Guide::find($guide_id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide non trouvé'
            ], 404);
        }

        // Vérifier autorisation (auteur ou admin role_id = 1)
        $user = Auth::user();
        if ($guide->author_id !== $user->id && $user->role_id != 1) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'images' => 'required|array',
            'images.*.id' => 'required|exists:guide_images,id',
            'images.*.order_position' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->images as $imageData) {
            GuideImage::where('id', $imageData['id'])
                ->where('guide_id', $guide_id)
                ->update(['order_position' => $imageData['order_position']]);
        }

        return response()->json([
            'message' => 'Ordre des images mis à jour avec succès'
        ]);
    }
}
