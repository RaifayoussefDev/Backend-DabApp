<?php

namespace App\Http\Controllers;

use App\Models\AdminMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminMenuController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/menus",
     *     operationId="getAdminMenus",
     *     tags={"Admin Menus Management"},
     *     summary="Get admin menu tree for authenticated user",
     *     description="Returns the complete menu tree filtered by user permissions. Menus are cached for 1 hour per user.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="force_refresh",
     *         in="query",
     *         description="Clear cache and fetch fresh data",
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved menu tree",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Dashboard"),
     *                     @OA\Property(property="icon", type="string", example="dashboard"),
     *                     @OA\Property(property="route", type="string", example="/admin/dashboard"),
     *                     @OA\Property(property="permission", type="string", example="view_dashboard"),
     *                     @OA\Property(property="order", type="integer", example=1),
     *                     @OA\Property(
     *                         property="children",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=21),
     *                             @OA\Property(property="name", type="string", example="All Users"),
     *                             @OA\Property(property="route", type="string", example="/admin/users"),
     *                             @OA\Property(property="permission", type="string", example="view_users")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $forceRefresh = $request->get('force_refresh', false);

            $cacheKey = "admin_menu_user_{$user->id}";

            if ($forceRefresh) {
                Cache::forget($cacheKey);
            }

            $menus = Cache::remember($cacheKey, 3600, function() use ($user) {
                return AdminMenu::buildTreeForUser($user);
            });

            return response()->json([
                'success' => true,
                'data' => $menus
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching admin menus', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching menus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/menus/all",
     *     operationId="getAllAdminMenus",
     *     tags={"Admin Menus Management"},
     *     summary="Get all admin menus (Admin only)",
     *     description="Returns all menus without permission filtering. For admin management purposes.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved all menus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="icon", type="string", nullable=true),
     *                     @OA\Property(property="route", type="string", nullable=true),
     *                     @OA\Property(property="permission", type="string", nullable=true),
     *                     @OA\Property(property="order", type="integer"),
     *                     @OA\Property(property="is_active", type="boolean"),
     *                     @OA\Property(property="children", type="array", @OA\Items(type="object"))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin only")
     * )
     */
    public function all()
    {
        try {
            $menus = AdminMenu::getTree();

            return response()->json([
                'success' => true,
                'data' => $menus
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching all admin menus', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching all menus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/menus",
     *     operationId="createAdminMenu",
     *     tags={"Admin Menus Management"},
     *     summary="Create a new menu item",
     *     description="Creates a new menu item (parent or child)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "order"},
     *             @OA\Property(property="parent_id", type="integer", nullable=true, example=null, description="Parent menu ID (null for top-level)"),
     *             @OA\Property(property="name", type="string", example="New Menu"),
     *             @OA\Property(property="icon", type="string", nullable=true, example="settings"),
     *             @OA\Property(property="route", type="string", nullable=true, example="/admin/new-feature"),
     *             @OA\Property(property="permission", type="string", nullable=true, example="view_new_feature"),
     *             @OA\Property(property="order", type="integer", example=10),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Menu created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Menu created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'parent_id' => 'nullable|exists:admin_menus,id',
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'route' => 'nullable|string|max:255',
            'permission' => 'nullable|string|max:100',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $menu = AdminMenu::create($request->all());

            // Clear all users' menu cache
            $this->clearAllMenuCache();

            return response()->json([
                'success' => true,
                'message' => 'Menu created successfully',
                'data' => $menu
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating menu', [
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating menu',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/menus/{id}",
     *     operationId="getAdminMenuById",
     *     tags={"Admin Menus Management"},
     *     summary="Get menu by ID",
     *     description="Returns a single menu item with its children",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Menu ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved menu",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Menu not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show($id)
    {
        try {
            $menu = AdminMenu::with('children')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $menu
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin/menus/{id}",
     *     operationId="updateAdminMenu",
     *     tags={"Admin Menus Management"},
     *     summary="Update a menu item",
     *     description="Updates an existing menu item",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Menu ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="parent_id", type="integer", nullable=true),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="icon", type="string", nullable=true),
     *             @OA\Property(property="route", type="string", nullable=true),
     *             @OA\Property(property="permission", type="string", nullable=true),
     *             @OA\Property(property="order", type="integer"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Menu updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Menu updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Menu not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(Request $request, $id)
    {
        $menu = AdminMenu::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'parent_id' => 'nullable|exists:admin_menus,id|not_in:' . $id,
            'name' => 'sometimes|required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'route' => 'nullable|string|max:255',
            'permission' => 'nullable|string|max:100',
            'order' => 'sometimes|required|integer|min:0',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $menu->update($request->all());

            // Clear all users' menu cache
            $this->clearAllMenuCache();

            return response()->json([
                'success' => true,
                'message' => 'Menu updated successfully',
                'data' => $menu->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating menu', [
                'menu_id' => $id,
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating menu',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/menus/{id}",
     *     operationId="deleteAdminMenu",
     *     tags={"Admin Menus Management"},
     *     summary="Delete a menu item",
     *     description="Deletes a menu item and all its children (cascade)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Menu ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Menu deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Menu deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Menu not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroy($id)
    {
        try {
            $menu = AdminMenu::findOrFail($id);
            $menu->delete();

            // Clear all users' menu cache
            $this->clearAllMenuCache();

            return response()->json([
                'success' => true,
                'message' => 'Menu deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting menu', [
                'menu_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting menu'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/menus/reorder",
     *     operationId="reorderAdminMenus",
     *     tags={"Admin Menus Management"},
     *     summary="Reorder menu items",
     *     description="Updates the order of multiple menu items at once",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"menus"},
     *             @OA\Property(
     *                 property="menus",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="order", type="integer", example=2)
     *                 ),
     *                 example={
     *                     {"id": 1, "order": 2},
     *                     {"id": 2, "order": 1},
     *                     {"id": 3, "order": 3}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Menus reordered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Menus reordered successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'menus' => 'required|array',
            'menus.*.id' => 'required|exists:admin_menus,id',
            'menus.*.order' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            foreach ($request->menus as $menuData) {
                AdminMenu::where('id', $menuData['id'])
                    ->update(['order' => $menuData['order']]);
            }

            // Clear all users' menu cache
            $this->clearAllMenuCache();

            return response()->json([
                'success' => true,
                'message' => 'Menus reordered successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error reordering menus', [
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error reordering menus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all users' menu cache
     */
    private function clearAllMenuCache()
    {
        // Pattern matching to clear all user menu caches
        Cache::flush(); // Or use a more targeted approach if needed
    }
}
