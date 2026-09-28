<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Shared helpers for the marketplace admin controllers.
 * Same access rule as the other admin controllers: auth.admin middleware + role_id = 1.
 */
abstract class MarketplaceAdminController extends Controller
{
    protected function forbidUnlessAdmin(): ?JsonResponse
    {
        if (Auth::user()?->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        return null;
    }

    /** null = "no pagination, return everything" (same convention as the guide admin endpoints). */
    protected function perPage(Request $request): ?int
    {
        $value = $request->input('per_page');

        return ($value === null || $value === '') ? null : min(max((int) $value, 1), 100);
    }

    protected function paginated(LengthAwarePaginator $page): array
    {
        return [
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
            ],
        ];
    }

    /** Unique against the whole table (soft-deleted rows included, they still hold the DB unique index). */
    protected function uniqueSlug(string $modelClass, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'item';
        $slug = $base;
        $n = 1;

        while ($modelClass::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    /**
     * Validation rule for an image field (logo_path, cover_image_path, image_path): the string returned by
     * POST /api/marketplace/upload-image, never a raw file. Loosely checked (must point at our own
     * marketplace storage) so an editor cannot hot-link an arbitrary external image.
     */
    protected function imagePathRule(): array
    {
        return ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
            if ($value && ! str_contains($value, '/storage/marketplace/')) {
                $fail('The ' . $attribute . ' must be a URL returned by POST /api/marketplace/upload-image.');
            }
        }];
    }
}
