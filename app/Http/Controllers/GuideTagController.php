<?php

namespace App\Http\Controllers;

use App\Models\GuideTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Guide Tags",
 *     description="API Endpoints for managing guide tags"
 * )
 */
class GuideTagController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/guides/tags",
     *     summary="List all tags",
     *     description="Retrieve the complete list of tags with associated guide counts",
     *     tags={"Guide Tags"},
     *     @OA\Response(
     *         response=200,
     *         description="List of tags retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Oil Change"),
     *                 @OA\Property(property="slug", type="string", example="oil-change"),
     *                 @OA\Property(property="guides_count", type="integer", example=12),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15 10:30:00")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $tags = GuideTag::withCount('guides')->get()->map(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'guides_count' => $tag->guides_count,
                'created_at' => $tag->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($tags);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/tags",
     *     summary="Create a new tag",
     *     description="Create a new guide tag. The slug is automatically generated from the name. Requires authentication.",
     *     tags={"Guide Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 maxLength=255,
     *                 example="Chain Maintenance",
     *                 description="Tag name (slug will be auto-generated)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tag created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tag créé avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=5),
     *                 @OA\Property(property="name", type="string", example="Chain Maintenance"),
     *                 @OA\Property(property="slug", type="string", example="chain-maintenance", description="Auto-generated"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15 10:30:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The name field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:guide_tags,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $tag = GuideTag::create($request->all());

        return response()->json([
            'message' => 'Tag created successfully',
            'data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'created_at' => $tag->created_at->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/tags/{slug}",
     *     summary="Show tag details",
     *     description="Retrieve detailed information of a specific tag by slug with associated guide count",
     *     tags={"Guide Tags"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Tag slug",
     *         @OA\Schema(type="string", example="oil-change")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tag details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Oil Change"),
     *             @OA\Property(property="slug", type="string", example="oil-change"),
     *             @OA\Property(property="guides_count", type="integer", example=12),
     *             @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15 10:30:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tag not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tag non trouvé")
     *         )
     *     )
     * )
     */
    public function show($slug)
    {
        $tag = GuideTag::where('slug', $slug)->withCount('guides')->first();

        if (!$tag) {
            return response()->json([
                'message' => 'Tag not found'
            ], 404);
        }

        return response()->json([
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'guides_count' => $tag->guides_count,
            'created_at' => $tag->created_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/guides/tags/{id}",
     *     summary="Update a tag",
     *     description="Update an existing tag information. If the name is changed, the slug will be automatically regenerated. Requires authentication.",
     *     tags={"Guide Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Tag ID to update",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 maxLength=255,
     *                 example="Engine Oil Change",
     *                 description="Tag name (slug will be auto-regenerated)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tag updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tag mis à jour avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Engine Oil Change"),
     *                 @OA\Property(property="slug", type="string", example="engine-oil-change", description="Auto-regenerated if name changes"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15 10:30:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tag not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tag non trouvé")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="This tag name already exists.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $tag = GuideTag::find($id);

        if (!$tag) {
            return response()->json([
                'message' => 'Tag not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:guide_tags,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $tag->update($request->all());

        return response()->json([
            'message' => 'Tag updated successfully',
            'data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'updated_at' => $tag->updated_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/guides/tags/{id}",
     *     summary="Delete a tag",
     *     description="Delete a guide tag. Requires authentication.",
     *     tags={"Guide Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Tag ID to delete",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tag deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tag supprimé avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tag not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tag non trouvé")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $tag = GuideTag::find($id);

        if (!$tag) {
            return response()->json([
                'message' => 'Tag not found'
            ], 404);
        }

        $tag->delete();

        return response()->json([
            'message' => 'Tag deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/tags/{slug}/guides",
     *     summary="Get all guides with a specific tag",
     *     description="Retrieve all published guides associated with a specific tag, sorted by publication date in descending order",
     *     tags={"Guide Tags"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Tag slug",
     *         @OA\Schema(type="string", example="oil-change")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of guides with the tag",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=10),
     *                 @OA\Property(property="title", type="string", example="How to change your motorcycle oil"),
     *                 @OA\Property(property="slug", type="string", example="how-to-change-motorcycle-oil"),
     *                 @OA\Property(property="excerpt", type="string", nullable=true, example="Complete guide for engine oil change"),
     *                 @OA\Property(property="featured_image", type="string", nullable=true, example="https://example.com/images/guide.jpg"),
     *                 @OA\Property(property="views_count", type="integer", example=1250),
     *                 @OA\Property(property="published_at", type="string", format="date-time", nullable=true, example="2025-01-10 14:30:00"),
     *                 @OA\Property(
     *                     property="author",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="Ahmed Benali"),
     *                     @OA\Property(property="profile_picture", type="string", nullable=true, example="https://example.com/profiles/ahmed.jpg")
     *                 ),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Maintenance"),
     *                     @OA\Property(property="slug", type="string", example="maintenance")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tag not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tag non trouvé")
     *         )
     *     )
     * )
     */
    public function getGuidesByTag($slug)
    {
        $tag = GuideTag::where('slug', $slug)->first();

        if (!$tag) {
            return response()->json([
                'message' => 'Tag not found'
            ], 404);
        }

        $guides = $tag->guides()
            ->with(['author', 'category'])
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->get()
            ->map(function ($guide) {
                return [
                    'id' => $guide->id,
                    'title' => $guide->title,
                    'slug' => $guide->slug,
                    'excerpt' => $guide->excerpt,
                    'featured_image' => $guide->featured_image,
                    'views_count' => $guide->views_count,
                    'published_at' => $guide->published_at ? $guide->published_at->format('Y-m-d H:i:s') : null,
                    'author' => [
                        'id' => $guide->author->id,
                        'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                        'profile_picture' => $guide->author->profile_picture,
                    ],
                    'category' => $guide->category ? [
                        'id' => $guide->category->id,
                        'name' => $guide->category->name,
                        'slug' => $guide->category->slug,
                    ] : null,
                ];
            });

        return response()->json($guides);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/tags/popular",
     *     summary="Get the most popular tags",
     *     description="Retrieve the top 10 most used tags sorted by guide count in descending order",
     *     tags={"Guide Tags"},
     *     @OA\Response(
     *         response=200,
     *         description="Popular tags retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Oil Change"),
     *                 @OA\Property(property="slug", type="string", example="oil-change"),
     *                 @OA\Property(property="guides_count", type="integer", example=25)
     *             )
     *         )
     *     )
     * )
     */
    public function popular()
    {
        $tags = GuideTag::withCount('guides')
            ->orderBy('guides_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'guides_count' => $tag->guides_count,
                ];
            });

        return response()->json($tags);
    }
}
