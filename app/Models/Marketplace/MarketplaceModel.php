<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base class for every marketplace_* table: created_at / updated_at / deleted_at.
 */
abstract class MarketplaceModel extends Model
{
    use SoftDeletes;

    /**
     * Public URL for an image column. Marketplace images are uploaded through the shared
     * POST /api/marketplace/upload-image endpoint (same pattern as listings): the column stores
     * the full URL that endpoint returned, as a plain string, ready to use as-is.
     * Still accepts a bare relative path (old rows seeded before this endpoint existed, e.g. the
     * demo seeder) and resolves it the legacy way, so nothing already in the database breaks.
     */
    protected function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : asset('storage/' . $path);
    }
}
