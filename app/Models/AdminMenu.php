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

    /**
     * Get the attributes that should be hidden for serialization.
     */
    protected function getHidden(): array
    {
        return ['created_at', 'updated_at'];
    }

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
        if ($this->permission) {
            $userPermissions = $user->getAllPermissions();

            if (is_object($userPermissions) && method_exists($userPermissions, 'pluck')) {
                $userPermissions = $userPermissions->pluck('name')->toArray();
            } elseif (!is_array($userPermissions)) {
                $userPermissions = [];
            }

            if (!in_array($this->permission, $userPermissions)) {
                return false;
            }
        }

        // Check roles
        if ($this->roles && !empty($this->roles)) {
            $userRoles = [];

            if ($user->roles) {
                if (is_object($user->roles) && method_exists($user->roles, 'pluck')) {
                    $userRoles = $user->roles->pluck('name')->toArray();
                } elseif (is_array($user->roles)) {
                    $userRoles = array_map(function($role) {
                        return is_object($role) ? $role->name : $role;
                    }, $user->roles);
                }
            }

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
        // Récupérer les permissions et rôles de l'utilisateur
        $userPermissions = $user->getAllPermissions();

        // Si c'est une collection, convertir en array
        if (is_object($userPermissions) && method_exists($userPermissions, 'pluck')) {
            $userPermissions = $userPermissions->pluck('name')->toArray();
        } elseif (!is_array($userPermissions)) {
            $userPermissions = [];
        }

        // Récupérer les rôles
        $userRoles = [];
        if ($user->roles) {
            if (is_object($user->roles) && method_exists($user->roles, 'pluck')) {
                $userRoles = $user->roles->pluck('name')->toArray();
            } elseif (is_array($user->roles)) {
                $userRoles = array_map(function($role) {
                    return is_object($role) ? $role->name : $role;
                }, $user->roles);
            }
        }

        return self::active()
            ->parents()
            ->with(['activeChildren' => function($query) use ($userPermissions, $userRoles) {
                $query->where(function($q) use ($userPermissions) {
                    // Check permission
                    $q->whereNull('permission');

                    if (!empty($userPermissions)) {
                        $q->orWhereIn('permission', $userPermissions);
                    }
                })
                ->where(function($q) use ($userRoles) {
                    // Check roles
                    $q->whereNull('roles');

                    if (!empty($userRoles)) {
                        foreach ($userRoles as $role) {
                            $q->orWhereJsonContains('roles', $role);
                        }
                    }
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
            ->values()
            ->toArray(); // Convertir en array pur
    }

    /**
     * Format menu item for frontend - RETOURNE TOUS LES CHAMPS
     */
    public function formatForFrontend(): array
    {
        $data = [
            'id' => (string)$this->id,
            'title' => $this->title ?? $this->name,
            'type' => $this->type,
            'translate' => $this->translate,
            'icon' => $this->icon,
            'hidden' => (bool)($this->attributes['hidden'] ?? false),
            'url' => $this->url ?? $this->route ?? $this->link,
            'classes' => $this->classes,
            'groupClasses' => $this->group_classes,
            'exactMatch' => (bool)$this->exact_match,
            'external' => (bool)$this->external,
            'target' => (bool)$this->target,
            'breadcrumbs' => (bool)$this->breadcrumbs,
            'link' => $this->link,
            'description' => $this->description,
            'path' => $this->path,
            'role' => $this->roles,
            'disabled' => (bool)$this->disabled,
            'isMainParent' => (bool)$this->is_main_parent,
            'order' => $this->order,
        ];

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
