<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RolePermissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/roles/{roleId}/permissions",
     *     operationId="getRolePermissions",
     *     tags={"Role Permissions"},
     *     summary="Get all permissions for a role",
     *     description="Returns all permissions assigned to a specific role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved role permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="role",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Admin")
     *                 ),
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="users.view"),
     *                         @OA\Property(property="description", type="string", example="View users list")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Missing permission")
     * )
     */
    public function index($roleId)
    {
        try {
            Log::info('Getting permissions for role', ['role_id' => $roleId]);

            $role = Role::with('permissions')->findOrFail($roleId);

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'created_at' => $role->created_at,
                        'updated_at' => $role->updated_at,
                    ],
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'description' => $permission->description,
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting role permissions', [
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving role permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/roles/{roleId}/permissions",
     *     operationId="assignPermissionToRole",
     *     tags={"Role Permissions"},
     *     summary="Assign permission to role",
     *     description="Assigns a single permission to a role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"permission_id"},
     *             @OA\Property(property="permission_id", type="integer", example=5, description="Permission ID to assign")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission assigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Permission assigned successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Permission already assigned or validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Permission already assigned to this role")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role or permission not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Missing permission")
     * )
     */
    public function store(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);

        $validator = Validator::make($request->all(), [
            'permission_id' => 'required|exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Check if already assigned
        if ($role->permissions()->where('permission_id', $request->permission_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission already assigned to this role'
            ], 400);
        }

        $role->givePermission($request->permission_id);

        return response()->json([
            'success' => true,
            'message' => 'Permission assigned successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/roles/{roleId}/permissions/sync",
     *     operationId="syncRolePermissions",
     *     tags={"Role Permissions"},
     *     summary="Sync all permissions for a role (replace all)",
     *     description="Replaces all current permissions with the provided list. Useful for updating all permissions at once.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"permission_ids"},
     *             @OA\Property(
     *                 property="permission_ids",
     *                 type="array",
     *                 description="Array of permission IDs to assign (replaces all existing)",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3, 5, 8}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions synced successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Permissions synced successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Admin"),
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="users.view")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Role not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Missing permission")
     * )
     */
    public function sync(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);

        $validator = Validator::make($request->all(), [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $role->syncPermissions($request->permission_ids);

        return response()->json([
            'success' => true,
            'message' => 'Permissions synced successfully',
            'data' => $role->load('permissions')
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/roles/{roleId}/permissions/{permissionId}",
     *     operationId="removePermissionFromRole",
     *     tags={"Role Permissions"},
     *     summary="Remove permission from role",
     *     description="Removes a specific permission from a role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="permissionId",
     *         in="path",
     *         required=true,
     *         description="Permission ID to remove",
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Permission removed successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role or permission not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Missing permission")
     * )
     */
    public function destroy($roleId, $permissionId)
    {
        $role = Role::findOrFail($roleId);
        $permission = Permission::findOrFail($permissionId);

        $role->removePermission($permissionId);

        return response()->json([
            'success' => true,
            'message' => 'Permission removed successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/roles/{roleId}/permissions/grouped",
     *     operationId="getRolePermissionsGrouped",
     *     tags={"Role Permissions"},
     *     summary="Get permissions grouped by interface",
     *     description="Returns permissions organized by interface (users, roles, etc.) with boolean values for each action",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved grouped permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="role",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Admin")
     *                 ),
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="object",
     *                     @OA\Property(
     *                         property="users",
     *                         type="object",
     *                         @OA\Property(property="view", type="boolean", example=true),
     *                         @OA\Property(property="create", type="boolean", example=true),
     *                         @OA\Property(property="update", type="boolean", example=true),
     *                         @OA\Property(property="delete", type="boolean", example=false)
     *                     ),
     *                     @OA\Property(
     *                         property="roles",
     *                         type="object",
     *                         @OA\Property(property="view", type="boolean", example=true),
     *                         @OA\Property(property="create", type="boolean", example=false),
     *                         @OA\Property(property="update", type="boolean", example=false),
     *                         @OA\Property(property="delete", type="boolean", example=false)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Missing permission")
     * )
     */
    public function getGroupedPermissions($roleId)
    {
        try {
            Log::info('Getting grouped permissions for role', ['role_id' => $roleId]);

            $role = Role::with('permissions')->findOrFail($roleId);

            // Récupérer les noms de toutes les permissions du rôle
            $rolePermissions = $role->permissions->pluck('name')->toArray();

            // Récupérer toutes les permissions disponibles
            $allPermissions = Permission::all();

            // Grouper les permissions par interface
            $grouped = [];

            foreach ($allPermissions as $permission) {
                // Extraire l'interface et l'action (ex: "users.view" -> interface="users", action="view")
                $parts = explode('.', $permission->name);

                if (count($parts) === 2) {
                    $interface = $parts[0];
                    $action = $parts[1];

                    // Initialiser l'interface si elle n'existe pas
                    if (!isset($grouped[$interface])) {
                        $grouped[$interface] = [
                            'view' => false,
                            'create' => false,
                            'update' => false,
                            'delete' => false,
                            'manage' => false, // Pour les permissions spéciales comme pricing.manage
                        ];
                    }

                    // Vérifier si le rôle a cette permission
                    $grouped[$interface][$action] = in_array($permission->name, $rolePermissions);
                }
            }

            // Nettoyer les interfaces qui n'ont que des valeurs false inutiles
            foreach ($grouped as $interface => &$actions) {
                // Supprimer les actions qui n'existent pas pour cette interface
                $actions = array_filter($actions, function ($value, $key) use ($allPermissions, $interface) {
                    return $allPermissions->contains('name', $interface . '.' . $key);
                }, ARRAY_FILTER_USE_BOTH);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'created_at' => $role->created_at,
                        'updated_at' => $role->updated_at,
                    ],
                    'permissions' => $grouped
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting grouped permissions', [
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving grouped permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/admin/roles/{roleId}/permissions/interface/{interface}",
     *     operationId="getRolePermissionsByInterface",
     *     tags={"Role Permissions"},
     *     summary="Get permissions for a specific interface",
     *     description="Returns permissions for a specific interface (users, roles, etc.) with boolean values for each CRUD action",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="interface",
     *         in="path",
     *         required=true,
     *         description="Interface name (users, roles, dashboard, etc.)",
     *         @OA\Schema(type="string", example="users")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved interface permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="role",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Admin")
     *                 ),
     *                 @OA\Property(property="interface", type="string", example="users"),
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="object",
     *                     @OA\Property(property="view", type="boolean", example=true),
     *                     @OA\Property(property="create", type="boolean", example=true),
     *                     @OA\Property(property="update", type="boolean", example=false),
     *                     @OA\Property(property="delete", type="boolean", example=false)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role not found or interface not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getPermissionsByInterface($roleId, $interface)
    {
        try {
            Log::info('Getting permissions for interface', [
                'role_id' => $roleId,
                'interface' => $interface
            ]);

            $role = Role::with('permissions')->findOrFail($roleId);

            // Récupérer les noms de toutes les permissions du rôle
            $rolePermissions = $role->permissions->pluck('name')->toArray();

            // Récupérer toutes les permissions pour cette interface
            $interfacePermissions = Permission::where('name', 'LIKE', $interface . '.%')->get();

            if ($interfacePermissions->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "No permissions found for interface: {$interface}"
                ], 404);
            }

            // Construire l'objet des permissions avec des booléens
            $permissions = [];

            foreach ($interfacePermissions as $permission) {
                // Extraire l'action (ex: "users.view" -> "view")
                $parts = explode('.', $permission->name);
                if (count($parts) === 2) {
                    $action = $parts[1]; // view, create, update, delete, manage

                    // Vérifier si le rôle a cette permission
                    $permissions[$action] = in_array($permission->name, $rolePermissions);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => [
                        'id' => $role->id,
                        'name' => $role->name,
                    ],
                    'interface' => $interface,
                    'permissions' => $permissions
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting interface permissions', [
                'role_id' => $roleId,
                'interface' => $interface,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving interface permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
