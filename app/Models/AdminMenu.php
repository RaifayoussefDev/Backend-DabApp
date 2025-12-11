<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminMenu extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'title',
        'type',
        'translate',
        'icon',
        'route',
        'link',
        'url',
        'path',
        'permission',
        'description',
        'classes',
        'group_classes',
        'hidden',
        'exact_match',
        'external',
        'target',
        'breadcrumbs',
        'disabled',
        'is_main_parent',
        'order',
        'is_active',
        'roles'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hidden' => 'boolean',
        'exact_match' => 'boolean',
        'external' => 'boolean',
        'target' => 'boolean',
        'breadcrumbs' => 'boolean',
        'disabled' => 'boolean',
        'is_main_parent' => 'boolean',
        'order' => 'integer',
        'roles' => 'array'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    /**
     * Parent menu relationship
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AdminMenu::class, 'parent_id');
    }

    /**
     * Children menus relationship
     */
    public function children(): HasMany
    {
        return $this->hasMany(AdminMenu::class, 'parent_id')->orderBy('order');
    }

    /**
     * Get active children
     */
    public function activeChildren(): HasMany
    {
        return $this->children()
            ->where('is_active', true)
            ->where('hidden', false)
            ->where('disabled', false);
    }

    /**
     * Scope to get only parent menus
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get active menus
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('hidden', false)
            ->where('disabled', false);
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if user has permission to access this menu
     */
    public function userHasAccess($user): bool
    {
        // Check permission
        if ($this->permission && !$user->hasPermissionTo($this->permission)) {
            return false;
        }

        // Check roles
        if ($this->roles && !empty($this->roles)) {
            $userRoles = $user->roles->pluck('name')->toArray();
            $hasRole = false;

            foreach ($this->roles as $role) {
                if (in_array($role, $userRoles)) {
                    $hasRole = true;
                    break;
                }
            }

            if (!$hasRole) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build menu tree for a specific user based on permissions and roles
     */
    public static function buildTreeForUser($user)
    {
        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        $userRoles = $user->roles->pluck('name')->toArray();

        return self::active()
            ->parents()
            ->with(['activeChildren' => function($query) use ($userPermissions, $userRoles) {
                $query->where(function($q) use ($userPermissions) {
                    // Check permission
                    $q->whereNull('permission')
                      ->orWhere(function($subQ) use ($userPermissions) {
                          foreach ($userPermissions as $permission) {
                              $subQ->orWhere('permission', $permission);
                          }
                      });
                })
                ->where(function($q) use ($userRoles) {
                    // Check roles
                    $q->whereNull('roles')
                      ->orWhere(function($subQ) use ($userRoles) {
                          foreach ($userRoles as $role) {
                              $subQ->orWhereJsonContains('roles', $role);
                          }
                      });
                });
            }])
            ->orderBy('order')
            ->get()
            ->filter(function($menu) use ($user) {
                // Filter parent if user doesn't have access
                if (!$menu->userHasAccess($user)) {
                    return false;
                }

                // Filter parent if no visible children and no route
                if ($menu->activeChildren->isEmpty() && !$menu->route && !$menu->url && !$menu->link) {
                    return false;
                }

                return true;
            })
            ->map(function($menu) {
                return $menu->formatForFrontend();
            })
            ->values();
    }

    /**
     * Format menu item for frontend
     */
    public function formatForFrontend(): array
    {
        $data = [
            'id' => (string)$this->id,
            'title' => $this->title ?? $this->name,
            'type' => $this->type,
            'icon' => $this->icon,
            'order' => $this->order,
        ];

        // Add optional fields only if they have values
        if ($this->translate) $data['translate'] = $this->translate;
        if ($this->hidden !== null) $data['hidden'] = $this->hidden;
        if ($this->url ?? $this->route ?? $this->link) {
            $data['url'] = $this->url ?? $this->route ?? $this->link;
        }
        if ($this->classes) $data['classes'] = $this->classes;
        if ($this->group_classes) $data['groupClasses'] = $this->group_classes;
        if ($this->exact_match) $data['exactMatch'] = $this->exact_match;
        if ($this->external) $data['external'] = $this->external;
        if ($this->target) $data['target'] = $this->target;
        if ($this->breadcrumbs !== null) $data['breadcrumbs'] = $this->breadcrumbs;
        if ($this->link) $data['link'] = $this->link;
        if ($this->description) $data['description'] = $this->description;
        if ($this->path) $data['path'] = $this->path;
        if ($this->roles) $data['role'] = $this->roles;
        if ($this->disabled) $data['disabled'] = $this->disabled;
        if ($this->is_main_parent) $data['isMainParent'] = $this->is_main_parent;

        // Add children if exists
        if ($this->relationLoaded('activeChildren') && $this->activeChildren->isNotEmpty()) {
            $data['children'] = $this->activeChildren->map(function($child) {
                return $child->formatForFrontend();
            })->toArray();
        }

        return $data;
    }

    /**
     * Get all menus as tree structure
     */
    public static function getTree()
    {
        return self::where('is_active', true)
            ->parents()
            ->with(['children' => function($query) {
                $query->where('is_active', true)->orderBy('order');
            }])
            ->orderBy('order')
            ->get();
    }
}
