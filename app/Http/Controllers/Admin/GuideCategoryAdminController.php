<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuideCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - Guide Categories",
 *     description="API Endpoints pour l'administration des catégories de guides"
 * )
 */
class GuideCategoryAdminController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/guide-categories",
     *     summary="Liste toutes les catégories (Admin)",
     *     description="Récupère toutes les catégories avec le nombre de guides. Si per_page est vide, retourne tout sans pagination.",
     *     tags={"Admin - Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Éléments par page (vide = tous)",
     *         required=false,
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche dans nom et description",
     *         required=false,
     *         @OA\Schema(type="string", example="maintenance")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des catégories",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Maintenance"),
     *                     @OA\Property(property="slug", type="string", example="maintenance"),
     *                     @OA\Property(property="description", type="string", example="Guides de maintenance"),
     *                     @OA\Property(property="icon", type="string", example="wrench"),
     *                     @OA\Property(property="color", type="string", example="#FF5722"),
     *                     @OA\Property(property="order_position", type="integer", example=1),
     *                     @OA\Property(property="guides_count", type="integer", example=45),
     *                     @OA\Property(property="created_at", type="string", example="2025-01-15 10:30:00")
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=12)
     *         )
     *     ),
     *     @OA\Response(response=403, description="Non autorisé")
     * )
     */
    public function index(Request $request)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $query = GuideCategory::withCount('guides');

        // Recherche
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query->orderBy('order_position');

        $perPage = $request->get('per_page');

        if (empty($perPage)) {
            $categories = $query->get();
            return response()->json([
                'data' => $categories,
                'total' => $categories->count()
            ]);
        } else {
            $categories = $query->paginate($perPage);
            return response()->json([
                'data' => $categories->items(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/guide-categories/stats",
     *     summary="Statistiques des catégories (Admin)",
     *     tags={"Admin - Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="total_categories", type="integer", example=12),
     *             @OA\Property(property="categories_with_guides", type="integer", example=10),
     *             @OA\Property(property="empty_categories", type="integer", example=2),
     *             @OA\Property(
     *                 property="top_categories",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="guides_count", type="integer")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function stats()
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $totalCategories = GuideCategory::count();
        $categoriesWithGuides = GuideCategory::has('guides')->count();

        return response()->json([
            'total_categories' => $totalCategories,
            'categories_with_guides' => $categoriesWithGuides,
            'empty_categories' => $totalCategories - $categoriesWithGuides,
            'top_categories' => GuideCategory::withCount('guides')
                ->orderBy('guides_count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($cat) {
                    return [
                        'id' => $cat->id,
                        'name' => $cat->name,
                        'slug' => $cat->slug,
                        'guides_count' => $cat->guides_count
                    ];
                })
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/guide-categories",
     *     summary="Créer une catégorie (Admin)",
     *     tags={"Admin - Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Sécurité"),
     *             @OA\Property(property="description", type="string", example="Guides de sécurité routière"),
     *             @OA\Property(property="icon", type="string", example="shield"),
     *             @OA\Property(property="color", type="string", example="#4CAF50"),
     *             @OA\Property(property="order_position", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Catégorie créée",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Category created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:guide_categories,name',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'order_position' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $count = 1;

        while (GuideCategory::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        $order = $request->order_position ?? GuideCategory::max('order_position') + 1;

        $category = GuideCategory::create([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'icon' => $request->icon,
            'color' => $request->color,
            'order_position' => $order,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/guide-categories/{id}",
     *     summary="Mettre à jour une catégorie (Admin)",
     *     tags={"Admin - Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Sécurité Routière"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="icon", type="string"),
     *             @OA\Property(property="color", type="string"),
     *             @OA\Property(property="order_position", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Catégorie mise à jour")
     * )
     */
    public function update(Request $request, $id)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $category = GuideCategory::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:guide_categories,name,' . $id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'order_position' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('name') && $request->name !== $category->name) {
            $slug = Str::slug($request->name);
            $originalSlug = $slug;
            $count = 1;

            while (GuideCategory::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $originalSlug . '-' . $count;
                $count++;
            }

            $category->slug = $slug;
        }

        $category->update($request->only(['name', 'description', 'icon', 'color', 'order_position']));

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/guide-categories/{id}",
     *     summary="Supprimer une catégorie (Admin)",
     *     description="Supprime une catégorie. Les guides associés auront category_id = NULL",
     *     tags={"Admin - Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Catégorie supprimée",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Category deleted successfully")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $category = GuideCategory::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/guide-categories/reorder",
     *     summary="Réorganiser l'ordre des catégories (Admin)",
     *     tags={"Admin - Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"categories"},
     *             @OA\Property(
     *                 property="categories",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="order_position", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Ordre mis à jour")
     * )
     */
    public function reorder(Request $request)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:guide_categories,id',
            'categories.*.order_position' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->categories as $catData) {
            GuideCategory::where('id', $catData['id'])->update(['order_position' => $catData['order_position']]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }
}
