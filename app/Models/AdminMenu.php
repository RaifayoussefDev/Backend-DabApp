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
        'icon',
        'route',
        'permission',
        'order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer'
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
        return $this->children()->where('is_active', true);
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
        return $query->where('is_active', true);
    }

    /**
     * Build menu tree for a specific user based on permissions
     */
    public static function buildTreeForUser($user)
    {
        $userPermissions = $user->getAllPermissions();

        return self::active()
            ->parents()
            ->with(['activeChildren' => function($query) use ($userPermissions) {
                $query->where(function($q) use ($userPermissions) {
                    $q->whereNull('permission')
                      ->orWhereIn('permission', $userPermissions);
                });
            }])
            ->orderBy('order')
            ->get()
            ->filter(function($menu) use ($userPermissions) {
                // Filter parent if user doesn't have permission
                if ($menu->permission && !in_array($menu->permission, $userPermissions)) {
                    return false;
                }

                // Filter parent if no visible children
                if ($menu->activeChildren->isEmpty() && !$menu->route) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * Get all menus as tree structure
     */
    public static function getTree()
    {
        return self::active()
            ->parents()
            ->with('activeChildren')
            ->orderBy('order')
            ->get();
    }
}
