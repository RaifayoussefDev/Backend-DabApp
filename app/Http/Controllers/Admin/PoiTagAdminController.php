<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoiTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - POI Tags",
 *     description="API Endpoints pour l'administration des tags de POI"
 * )
 */
class PoiTagAdminController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/poi-tags",
     *     summary="Liste tous les tags de POI (Admin)",
     *     description="Récupère tous les tags avec le nombre de POIs associés",
     *     tags={"Admin - POI Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Vide = tous", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", example="station")),
     *     @OA\Response(response=200, description="Liste des tags")
     * )
     */
    public function index(Request $request)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $query = PoiTag::withCount('pois');

        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $query->orderBy('name');

        $perPage = $request->get('per_page');

        if (empty($perPage)) {
            $tags = $query->get();
            return response()->json(['data' => $tags, 'total' => $tags->count()]);
        } else {
            $tags = $query->paginate($perPage);
            return response()->json([
                'data' => $tags->items(),
                'current_page' => $tags->currentPage(),
                'last_page' => $tags->lastPage(),
                'per_page' => $tags->perPage(),
                'total' => $tags->total(),
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/poi-tags/stats",
     *     summary="Statistiques des tags de POI (Admin)",
     *     tags={"Admin - POI Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Statistiques")
     * )
     */
    public function stats()
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $totalTags = PoiTag::count();
        $tagsWithPois = PoiTag::has('pois')->count();

        return response()->json([
            'total_tags' => $totalTags,
            'tags_with_pois' => $tagsWithPois,
            'unused_tags' => $totalTags - $tagsWithPois,
            'top_tags' => PoiTag::withCount('pois')
                ->orderBy('pois_count', 'desc')
                ->limit(10)
                ->get()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/poi-tags",
     *     summary="Créer un tag de POI (Admin)",
     *     tags={"Admin - POI Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Station 24/7")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tag créé")
     * )
     */
    public function store(Request $request)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:poi_tags,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $count = 1;

        while (PoiTag::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        $tag = PoiTag::create(['name' => $request->name, 'slug' => $slug]);

        return response()->json(['message' => 'Tag created successfully', 'data' => $tag], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/poi-tags/{id}",
     *     summary="Mettre à jour un tag de POI (Admin)",
     *     tags={"Admin - POI Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tag mis à jour")
     * )
     */
    public function update(Request $request, $id)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $tag = PoiTag::find($id);
        if (!$tag) {
            return response()->json(['message' => 'Tag not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:poi_tags,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $count = 1;

        while (PoiTag::where('slug', $slug)->where('id', '!=', $id)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        $tag->update(['name' => $request->name, 'slug' => $slug]);

        return response()->json(['message' => 'Tag updated successfully', 'data' => $tag]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/poi-tags/{id}",
     *     summary="Supprimer un tag de POI (Admin)",
     *     tags={"Admin - POI Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tag supprimé")
     * )
     */
    public function destroy($id)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $tag = PoiTag::find($id);
        if (!$tag) {
            return response()->json(['message' => 'Tag not found'], 404);
        }

        DB::table('poi_tag_relations')->where('tag_id', $id)->delete();
        $tag->delete();

        return response()->json(['message' => 'Tag deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/poi-tags/bulk-delete",
     *     summary="Suppression en masse de tags de POI (Admin)",
     *     tags={"Admin - POI Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Tags supprimés")
     * )
     */
    public function bulkDelete(Request $request)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:poi_tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::table('poi_tag_relations')->whereIn('tag_id', $request->ids)->delete();
        PoiTag::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => 'Tags deleted successfully', 'deleted_count' => count($request->ids)]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/poi-tags/cleanup",
     *     summary="Supprimer tous les tags de POI non utilisés (Admin)",
     *     tags={"Admin - POI Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Tags non utilisés supprimés")
     * )
     */
    public function cleanup()
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $count = PoiTag::doesntHave('pois')->count();
        PoiTag::doesntHave('pois')->delete();

        return response()->json(['message' => 'Unused tags deleted successfully', 'deleted_count' => $count]);
    }
}
