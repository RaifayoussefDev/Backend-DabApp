<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait UserFilterTrait
{
    /**
     * Scope a query to apply advanced user filters.
     *
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function scopeApplyFilters(Builder $query, array $filters)
    {
        $query->where('is_active', true);

        // 0. Filter by specific User IDs
        if (!empty($filters['user_ids'])) {
            $query->whereIn('id', $filters['user_ids']);
        }

        // 1. Basic User Attributes
        if (isset($filters['is_verified'])) {
            $query->where('verified', (bool) $filters['is_verified']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (!empty($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        if (!empty($filters['country_id'])) {
            $query->where('country_id', $filters['country_id']);
        }

        // 2. Last Login Filter
        if (!empty($filters['last_login_from'])) {
            $query->where('last_login', '>=', $filters['last_login_from']);
        }

        // 3. Registration Date Range
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // 4. Listing Criteria (Nested)
        if (!empty($filters['category_id']) || !empty($filters['listing_date_from']) || !empty($filters['listing_date_to']) || isset($filters['has_listing'])) {
            if (isset($filters['has_listing']) && $filters['has_listing'] === false) {
                $query->doesntHave('listings');
            } else {
                $query->whereHas('listings', function ($q) use ($filters) {
                    if (!empty($filters['category_id'])) {
                        $q->where('category_id', $filters['category_id']);
                    }
                    if (!empty($filters['listing_date_from'])) {
                        $q->where('created_at', '>=', $filters['listing_date_from']);
                    }
                    if (!empty($filters['listing_date_to'])) {
                        $q->where('created_at', '<=', $filters['listing_date_to']);
                    }
                });
            }
        }

        // 5. Garage Filter
        if (!empty($filters['brand_in_garage'])) {
            $query->whereHas('myGarage', function ($q) use ($filters) {
                $q->where('brand_id', $filters['brand_in_garage']);
            });
        }

        // 6. Points of Interest Filter
        if (isset($filters['has_points_of_interest'])) {
            if ($filters['has_points_of_interest']) {
                $query->has('pointsOfInterest');
            } else {
                $query->doesntHave('pointsOfInterest');
            }
        }

        return $query;
    }
}
