<?php

namespace App\Http\Controllers;

use App\Models\GuideCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Guide Categories",
 *     description="API Endpoints for managing guide categories"
 * )
 */
class GuideCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/guides/categories",
     *     summary="List all guide categories",
     *     description="Retrieve the complete list of categories with associated guide counts",
     *     tags={"Guide Categories"},
     *     @OA\Response(
     *         response=200,
     *         description="List of categories retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Maintenance"),
     *                 @OA\Property(property="slug", type="string", example="maintenance"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Motorcycle maintenance guides"),
     *                 @OA\Property(property="icon", type="string", nullable=true, example="wrench"),
     *                 @OA\Property(property="color", type="string", nullable=true, example="#FF5722"),
     *                 @OA\Property(property="guides_count", type="integer", example=15),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15 10:30:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15 10:30:00")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $categories = GuideCategory::withCount('guides')->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'color' => $category->color,
                'guides_count' => $category->guides_count,
                'created_at' => $category->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $category->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($categories);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/categories",
     *     summary="Create a new category",
     *     description="Create a new guide category. The slug is automatically generated from the name. Requires authentication.",
     *     tags={"Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 maxLength=255,
     *                 example="Road Safety",
     *                 description="Category name (slug will be auto-generated)"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 nullable=true,
     *                 example="Tips and guides for safe riding"
     *             ),
     *             @OA\Property(
     *                 property="icon",
     *                 type="string",
     *                 maxLength=255,
     *                 nullable=true,
     *                 example="shield"
     *             ),
     *             @OA\Property(
     *                 property="color",
     *                 type="string",
     *                 maxLength=50,
     *                 nullable=true,
     *                 example="#4CAF50"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie créée avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=5),
     *                 @OA\Property(property="name", type="string", example="Road Safety"),
     *                 @OA\Property(property="slug", type="string", example="road-safety", description="Auto-generated"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Tips and guides for safe riding"),
     *                 @OA\Property(property="icon", type="string", nullable=true, example="shield"),
     *                 @OA\Property(property="color", type="string", nullable=true, example="#4CAF50"),
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
            'name' => 'required|string|max:255|unique:guide_categories,name',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $category = GuideCategory::create($request->all());

        return response()->json([
            'message' => 'Category created successfully',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'color' => $category->color,
                'created_at' => $category->created_at->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/categories/{id}",
     *     summary="Show category details",
     *     description="Retrieve detailed information of a specific category with associated guide count",
     *     tags={"Guide Categories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Maintenance"),
     *             @OA\Property(property="slug", type="string", example="maintenance"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Motorcycle maintenance guides"),
     *             @OA\Property(property="icon", type="string", nullable=true, example="wrench"),
     *             @OA\Property(property="color", type="string", nullable=true, example="#FF5722"),
     *             @OA\Property(property="guides_count", type="integer", example=15),
     *             @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15 10:30:00"),
     *             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15 10:30:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie non trouvée")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $category = GuideCategory::withCount('guides')->find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'icon' => $category->icon,
            'color' => $category->color,
            'guides_count' => $category->guides_count,
            'created_at' => $category->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $category->updated_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/guides/categories/{id}",
     *     summary="Update a category",
     *     description="Update an existing category information. If the name is changed, the slug will be automatically regenerated. Requires authentication.",
     *     tags={"Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Category ID to update",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 maxLength=255,
     *                 example="Motorcycle Maintenance",
     *                 description="Category name (slug will be auto-regenerated)"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 nullable=true,
     *                 example="Complete maintenance guides for motorcycles"
     *             ),
     *             @OA\Property(
     *                 property="icon",
     *                 type="string",
     *                 maxLength=255,
     *                 nullable=true,
     *                 example="tool"
     *             ),
     *             @OA\Property(
     *                 property="color",
     *                 type="string",
     *                 maxLength=50,
     *                 nullable=true,
     *                 example="#2196F3"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie mise à jour avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Motorcycle Maintenance"),
     *                 @OA\Property(property="slug", type="string", example="motorcycle-maintenance", description="Auto-regenerated if name changes"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Complete maintenance guides for motorcycles"),
     *                 @OA\Property(property="icon", type="string", nullable=true, example="tool"),
     *                 @OA\Property(property="color", type="string", nullable=true, example="#2196F3"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15 10:30:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie non trouvée")
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
     *                     @OA\Items(type="string", example="This category name already exists.")
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
        $category = GuideCategory::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:guide_categories,name,' . $id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $category->update($request->all());

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'color' => $category->color,
                'updated_at' => $category->updated_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/guides/categories/{id}",
     *     summary="Delete a category",
     *     description="Delete a guide category. Requires authentication.",
     *     tags={"Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Category ID to delete",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie supprimée avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie non trouvée")
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
        $category = GuideCategory::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/categories/{id}/guides",
     *     summary="Get all guides from a category",
     *     description="Retrieve all published guides belonging to a specific category, sorted by publication date in descending order",
     *     tags={"Guide Categories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of guides from the category",
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
     *                 @OA\Property(property="likes_count", type="integer", example=85),
     *                 @OA\Property(property="comments_count", type="integer", example=23),
     *                 @OA\Property(property="published_at", type="string", format="date-time", example="2025-01-10 14:30:00"),
     *                 @OA\Property(
     *                     property="author",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="Ahmed Benali"),
     *                     @OA\Property(property="profile_picture", type="string", nullable=true, example="https://example.com/profiles/ahmed.jpg")
     *                 ),
     *                 @OA\Property(
     *                     property="tags",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Maintenance"),
     *                         @OA\Property(property="slug", type="string", example="maintenance")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie non trouvée")
     *         )
     *     )
     * )
     */
    public function getGuidesByCategory($id)
    {
        $category = GuideCategory::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }

        $guides = $category->guides()
            ->with(['author', 'tags'])
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
                    'likes_count' => $guide->likes()->count(),
                    'comments_count' => $guide->allComments()->count(),
                    'published_at' => $guide->published_at->format('Y-m-d H:i:s'),
                    'author' => [
                        'id' => $guide->author->id,
                        'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                        'profile_picture' => $guide->author->profile_picture,
                    ],
                    'tags' => $guide->tags->map(function ($tag) {
                        return [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'slug' => $tag->slug,
                        ];
                    }),
                ];
            });

        return response()->json($guides);
    }
}
