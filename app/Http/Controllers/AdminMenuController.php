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
     *     description="Returns the complete menu tree filtered by user permissions and roles. Menus are cached for 1 hour per user.",
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
     *                     @OA\Property(property="id", type="string", example="1"),
     *                     @OA\Property(property="title", type="string", example="Dashboard"),
     *                     @OA\Property(property="type", type="string", enum={"item", "collapse", "group"}, example="collapse"),
     *                     @OA\Property(property="translate", type="string", example="NAV.DASHBOARD"),
     *                     @OA\Property(property="icon", type="string", example="dashboard"),
     *                     @OA\Property(property="url", type="string", example="/admin/dashboard"),
     *                     @OA\Property(property="classes", type="string", example="nav-item"),
     *                     @OA\Property(property="groupClasses", type="string", example="group-item"),
     *                     @OA\Property(property="hidden", type="boolean", example=false),
     *                     @OA\Property(property="exactMatch", type="boolean", example=false),
     *                     @OA\Property(property="external", type="boolean", example=false),
     *                     @OA\Property(property="target", type="boolean", example=false),
     *                     @OA\Property(property="breadcrumbs", type="boolean", example=true),
     *                     @OA\Property(property="link", type="string", example="/dashboard"),
     *                     @OA\Property(property="description", type="string", example="Main dashboard"),
     *                     @OA\Property(property="path", type="string", example="dashboard"),
     *                     @OA\Property(property="role", type="array", @OA\Items(type="string", example="admin")),
     *                     @OA\Property(property="disabled", type="boolean", example=false),
     *                     @OA\Property(property="isMainParent", type="boolean", example=true),
     *                     @OA\Property(property="order", type="integer", example=1),
     *                     @OA\Property(
     *                         property="children",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="string", example="21"),
     *                             @OA\Property(property="title", type="string", example="All Users"),
     *                             @OA\Property(property="type", type="string", example="item"),
     *                             @OA\Property(property="url", type="string", example="/admin/users"),
     *                             @OA\Property(property="icon", type="string", example="users")
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
            $forceRefresh = $request->boolean('force_refresh', false);

            $cacheKey = "admin_menu_user_{$user->id}";

            if ($forceRefresh) {
                Cache::forget($cacheKey);
            }

            $menus = Cache::remember($cacheKey, 3600, function() use ($user) {
                return AdminMenu::buildTreeForUser($user);
            });

            // Return as raw array, not through JSON resource
            return response()->json([
                'success' => true,
                'data' => $menus
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (\Exception $e) {
            Log::error('Error fetching admin menus', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching menus',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
     *                     @OA\Property(property="title", type="string", nullable=true),
     *                     @OA\Property(property="type", type="string", enum={"item", "collapse", "group"}),
     *                     @OA\Property(property="translate", type="string", nullable=true),
     *                     @OA\Property(property="icon", type="string", nullable=true),
     *                     @OA\Property(property="route", type="string", nullable=true),
     *                     @OA\Property(property="link", type="string", nullable=true),
     *                     @OA\Property(property="url", type="string", nullable=true),
     *                     @OA\Property(property="path", type="string", nullable=true),
     *                     @OA\Property(property="permission", type="string", nullable=true),
     *                     @OA\Property(property="description", type="string", nullable=true),
     *                     @OA\Property(property="classes", type="string", nullable=true),
     *                     @OA\Property(property="group_classes", type="string", nullable=true),
     *                     @OA\Property(property="hidden", type="boolean"),
     *                     @OA\Property(property="exact_match", type="boolean"),
     *                     @OA\Property(property="external", type="boolean"),
     *                     @OA\Property(property="target", type="boolean"),
     *                     @OA\Property(property="breadcrumbs", type="boolean"),
     *                     @OA\Property(property="disabled", type="boolean"),
     *                     @OA\Property(property="is_main_parent", type="boolean"),
     *                     @OA\Property(property="order", type="integer"),
     *                     @OA\Property(property="is_active", type="boolean"),
     *                     @OA\Property(property="roles", type="array", @OA\Items(type="string")),
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
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/menus",
     *     operationId="createAdminMenu",
     *     tags={"Admin Menus Management"},
     *     summary="Create a new menu item",
     *     description="Creates a new menu item (parent or child) with all available properties",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "type", "order"},
     *             @OA\Property(property="parent_id", type="integer", nullable=true, example=null, description="Parent menu ID (null for top-level)"),
     *             @OA\Property(property="name", type="string", example="New Menu", description="Internal name"),
     *             @OA\Property(property="title", type="string", nullable=true, example="New Menu Item", description="Display title"),
     *             @OA\Property(property="type", type="string", enum={"item", "collapse", "group"}, example="item", description="Menu type"),
     *             @OA\Property(property="translate", type="string", nullable=true, example="NAV.NEW_MENU", description="Translation key"),
     *             @OA\Property(property="icon", type="string", nullable=true, example="settings", description="Icon name"),
     *             @OA\Property(property="route", type="string", nullable=true, example="/admin/new-feature", description="Route path"),
     *             @OA\Property(property="link", type="string", nullable=true, example="/new-feature", description="Alternative link"),
     *             @OA\Property(property="url", type="string", nullable=true, example="https://example.com", description="Direct URL"),
     *             @OA\Property(property="path", type="string", nullable=true, example="new-feature", description="Path reference"),
     *             @OA\Property(property="permission", type="string", nullable=true, example="view_new_feature", description="Required permission"),
     *             @OA\Property(property="description", type="string", nullable=true, example="This is a new feature", description="Menu description"),
     *             @OA\Property(property="classes", type="string", nullable=true, example="custom-class", description="CSS classes"),
     *             @OA\Property(property="group_classes", type="string", nullable=true, example="group-custom", description="Group CSS classes"),
     *             @OA\Property(property="hidden", type="boolean", example=false, description="Is hidden"),
     *             @OA\Property(property="exact_match", type="boolean", example=false, description="Exact route match"),
     *             @OA\Property(property="external", type="boolean", example=false, description="Is external link"),
     *             @OA\Property(property="target", type="boolean", example=false, description="Open in new tab"),
     *             @OA\Property(property="breadcrumbs", type="boolean", example=true, description="Show in breadcrumbs"),
     *             @OA\Property(property="disabled", type="boolean", example=false, description="Is disabled"),
     *             @OA\Property(property="is_main_parent", type="boolean", example=false, description="Is main parent"),
     *             @OA\Property(property="order", type="integer", example=10, description="Display order"),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Is active"),
     *             @OA\Property(
     *                 property="roles",
     *                 type="array",
     *                 @OA\Items(type="string", example="admin"),
     *                 description="Allowed roles",
     *                 nullable=true
     *             )
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
            'title' => 'nullable|string|max:100',
            'type' => 'required|in:item,collapse,group',
            'translate' => 'nullable|string|max:100',
            'icon' => 'nullable|string|max:50',
            'route' => 'nullable|string|max:255',
            'link' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:255',
            'permission' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'classes' => 'nullable|string|max:100',
            'group_classes' => 'nullable|string|max:100',
            'hidden' => 'boolean',
            'exact_match' => 'boolean',
            'external' => 'boolean',
            'target' => 'boolean',
            'breadcrumbs' => 'boolean',
            'disabled' => 'boolean',
            'is_main_parent' => 'boolean',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'string|max:50'
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
                'data' => $menu->load('children')
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating menu', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating menu',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/menus/{id}",
     *     operationId="getAdminMenuById",
     *     tags={"Admin Menus Management"},
     *     summary="Get menu by ID",
     *     description="Returns a single menu item with its children and all properties",
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
     *     description="Updates an existing menu item with any of the available properties",
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
     *             @OA\Property(property="title", type="string", nullable=true),
     *             @OA\Property(property="type", type="string", enum={"item", "collapse", "group"}),
     *             @OA\Property(property="translate", type="string", nullable=true),
     *             @OA\Property(property="icon", type="string", nullable=true),
     *             @OA\Property(property="route", type="string", nullable=true),
     *             @OA\Property(property="link", type="string", nullable=true),
     *             @OA\Property(property="url", type="string", nullable=true),
     *             @OA\Property(property="path", type="string", nullable=true),
     *             @OA\Property(property="permission", type="string", nullable=true),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="classes", type="string", nullable=true),
     *             @OA\Property(property="group_classes", type="string", nullable=true),
     *             @OA\Property(property="hidden", type="boolean"),
     *             @OA\Property(property="exact_match", type="boolean"),
     *             @OA\Property(property="external", type="boolean"),
     *             @OA\Property(property="target", type="boolean"),
     *             @OA\Property(property="breadcrumbs", type="boolean"),
     *             @OA\Property(property="disabled", type="boolean"),
     *             @OA\Property(property="is_main_parent", type="boolean"),
     *             @OA\Property(property="order", type="integer"),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"))
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
        try {
            $menu = AdminMenu::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'parent_id' => 'nullable|exists:admin_menus,id|not_in:' . $id,
                'name' => 'sometimes|required|string|max:100',
                'title' => 'nullable|string|max:100',
                'type' => 'sometimes|required|in:item,collapse,group',
                'translate' => 'nullable|string|max:100',
                'icon' => 'nullable|string|max:50',
                'route' => 'nullable|string|max:255',
                'link' => 'nullable|string|max:255',
                'url' => 'nullable|string|max:255',
                'path' => 'nullable|string|max:255',
                'permission' => 'nullable|string|max:100',
                'description' => 'nullable|string',
                'classes' => 'nullable|string|max:100',
                'group_classes' => 'nullable|string|max:100',
                'hidden' => 'boolean',
                'exact_match' => 'boolean',
                'external' => 'boolean',
                'target' => 'boolean',
                'breadcrumbs' => 'boolean',
                'disabled' => 'boolean',
                'is_main_parent' => 'boolean',
                'order' => 'sometimes|required|integer|min:0',
                'is_active' => 'boolean',
                'roles' => 'nullable|array',
                'roles.*' => 'string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 400);
            }

            $menu->update($request->all());

            // Clear all users' menu cache
            $this->clearAllMenuCache();

            return response()->json([
                'success' => true,
                'message' => 'Menu updated successfully',
                'data' => $menu->fresh()->load('children')
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating menu', [
                'menu_id' => $id,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating menu',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/menus/{id}",
     *     operationId="deleteAdminMenu",
     *     tags={"Admin Menus Management"},
     *     summary="Delete a menu item",
     *     description="Deletes a menu item and all its children (cascade delete)",
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
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting menu', [
                'menu_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting menu',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
            'menus' => 'required|array|min:1',
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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error reordering menus',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/menus/bulk-delete",
     *     operationId="bulkDeleteAdminMenus",
     *     tags={"Admin Menus Management"},
     *     summary="Bulk delete menu items",
     *     description="Delete multiple menu items at once",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(
     *                 property="ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Menus deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="3 menus deleted successfully"),
     *             @OA\Property(property="deleted_count", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:admin_menus,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $deletedCount = AdminMenu::whereIn('id', $request->ids)->delete();

            // Clear all users' menu cache
            $this->clearAllMenuCache();

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} menus deleted successfully",
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error bulk deleting menus', [
                'ids' => $request->ids,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting menus',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Clear all users' menu cache
     */
    private function clearAllMenuCache()
    {
        try {
            // Try to use cache tags if supported
            Cache::tags(['admin_menus'])->flush();
        } catch (\Exception $e) {
            // Fallback: clear specific pattern or all cache
            Log::warning('Cache tags not supported, clearing all cache', [
                'error' => $e->getMessage()
            ]);

            // Or use a more manual approach to clear user-specific caches
            // This would require storing a list of user IDs somewhere
        }
    }
}
