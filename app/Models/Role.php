<?php
// app/Models/Role.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Role",
 *     type="object",
 *     title="Role",
 *     required={"name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Admin"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * Users with this role
     */
    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }

    /**
     * Permissions for this role
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission($permissionName)
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    /**
     * Assign permission to role
     */
    public function givePermission($permissionId)
    {
        return $this->permissions()->attach($permissionId);
    }

    /**
     * Remove permission from role
     */
    public function removePermission($permissionId)
    {
        return $this->permissions()->detach($permissionId);
    }

    /**
     * Sync permissions (replace all)
     */
    public function syncPermissions($permissionIds)
    {
        return $this->permissions()->sync($permissionIds);
    }
}
