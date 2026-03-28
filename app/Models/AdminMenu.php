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

    // CORRECTION: Utiliser $hidden comme propriété
    protected $hidden = ['created_at', 'updated_at'];

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
     * VERSION SIMPLIFIÉE : BASÉE UNIQUEMENT SUR LES RÔLES
     */
    public function userHasAccess($user): bool
    {
        // 1. System Admin Bypass: If the user has the 'admin' role (ID 1), they see everything.
        if ($user->role_id === 1) {
            return true;
        }

        // 2. Role-Based Access: Check if the user's role is in the authorized roles array
        if ($this->roles && !empty($this->roles)) {
            $userRoleName = $user->role ? $user->role->name : null;

            if ($userRoleName && in_array($userRoleName, $this->roles)) {
                return true;
            }
            
            return false;
        }

        // 3. Default: If no roles are defined, it's public (rare for admin menus).
        return true;
    }

    public static function buildTreeForUser($user)
    {
        // Récupérer le nom du rôle de l'utilisateur
        $userRoleName = $user->role ? $user->role->name : null;
        $isAdmin = ($user->role_id === 1);

        $query = self::active()->parents();

        // Si ce n'est pas un admin (ID 1), filtrer par rôles
        if (!$isAdmin && $userRoleName) {
            $query->where(function($q) use ($userRoleName) {
                $q->whereNull('roles')
                  ->orWhereJsonContains('roles', $userRoleName);
            });
        }

        return $query->with(['activeChildren' => function($query) use ($userRoleName, $isAdmin) {
                if (!$isAdmin && $userRoleName) {
                    $query->where(function($q) use ($userRoleName) {
                        $q->whereNull('roles')
                          ->orWhereJsonContains('roles', $userRoleName);
                    });
                }
            }])
            ->orderBy('order')
            ->get()
            ->filter(function($menu) use ($user) {
                // Filter parent if user doesn't have access
                if (!$menu->userHasAccess($user)) {
                    return false;
                }

                // Filter parent if no visible children and no navigation destination
                if ($menu->activeChildren->isEmpty() && !$menu->route && !$menu->url && !$menu->link && !$menu->path) {
                    return false;
                }

                return true;
            })
            ->map(function($menu) {
                return $menu->formatForFrontend();
            })
            ->values()
            ->toArray();
    }

    /**
     * Format menu item for frontend - RETOURNE TOUS LES CHAMPS
     * Utilise getRawOriginal() pour éviter le conflit avec $hidden
     */
    public function formatForFrontend(): array
    {
        $data = [
            'id' => (string)$this->id,
            'title' => $this->title ?? $this->name,
            'type' => $this->type,
            'translate' => $this->translate,
            'icon' => $this->icon,
            'hidden' => (bool)$this->getRawOriginal('hidden'), // Utiliser getRawOriginal au lieu de $this->attributes
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
            'permission' => $this->permission,
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
            ->get()
            ->map(function($menu) {
                // Manually load children for formatForFrontend to pick up
                // Note: activeChildren was used in index, but children is safer for management
                $menu->setRelation('activeChildren', $menu->children);
                return $menu->formatForFrontend();
            })
            ->values()
            ->toArray();
    }
}
