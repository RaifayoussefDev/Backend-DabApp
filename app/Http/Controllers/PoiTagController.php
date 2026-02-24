<?php

namespace App\Http\Controllers;

use App\Models\PoiTag;
use App\Models\PointOfInterest;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="POI Tags",
 *     description="API Endpoints pour les tags de POI"
 * )
 */
class PoiTagController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/poi-tags",
     *     summary="Liste tous les tags de POI",
     *     description="Récupère tous les tags de POI publics",
     *     tags={"POI Tags"},
     *     @OA\Response(response=200, description="Liste des tags")
     * )
     */
    public function index()
    {
        $tags = PoiTag::orderBy('name')->get();
        return response()->json([
            'success' => true,
            'data' => $tags
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/poi-tags/popular",
     *     summary="Liste les tags de POI populaires",
     *     tags={"POI Tags"},
     *     @OA\Response(response=200, description="Liste des tags populaires")
     * )
     */
    public function popular()
    {
        $tags = PoiTag::withCount('pois')
            ->orderBy('pois_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tags
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/poi-tags/{slug}",
     *     summary="Détails d'un tag de POI",
     *     tags={"POI Tags"},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Détails du tag")
     * )
     */
    public function show($slug)
    {
        $tag = PoiTag::where('slug', $slug)->firstOrFail();
        return response()->json([
            'success' => true,
            'data' => $tag
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/poi-tags/{slug}/pois",
     *     summary="Liste les POIs par tag",
     *     tags={"POI Tags"},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Liste des POIs")
     * )
     */
    public function getPoisByTag($slug)
    {
        $tag = PoiTag::where('slug', $slug)->firstOrFail();
        $pois = $tag->pois()->with(['type', 'city', 'country'])->paginate(15);

        return response()->json($pois);
    }
}
