<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait UserFilterTrait
{
    /**
     * Build a user query based on standard notification filters.
     *
     * @param array $filters
     * @return Builder
     */
    protected function buildFilteredUserQuery(array $filters): Builder
    {
        $query = User::query()->where('is_active', true);

        // 0. Filter by specific User IDs (Direct Notification)
        if (!empty($filters['user_ids'])) {
            $query->whereIn('id', $filters['user_ids']);
        }

        // 1. Filter by Country
        if (!empty($filters['country_id'])) {
            $query->where('country_id', $filters['country_id']);
        }

        // 2. Filter by Listing Criteria
        if (!empty($filters['category_id']) || !empty($filters['date_from']) || !empty($filters['date_to'])) {
            $query->whereHas('listings', function ($q) use ($filters) {
                if (!empty($filters['category_id'])) {
                    $q->where('category_id', $filters['category_id']);
                }
                if (!empty($filters['date_from'])) {
                    $q->where('created_at', '>=', $filters['date_from']);
                }
                if (!empty($filters['date_to'])) {
                    $q->where('created_at', '<=', $filters['date_to']);
                }
            });
        }

        // 3. Filter by "Has Listing"
        if (isset($filters['has_listing'])) {
            if ($filters['has_listing']) {
                $query->has('listings');
            } else {
                $query->doesntHave('listings');
            }
        }

        // 4. Filter by Brand in Garage
        if (!empty($filters['brand_in_garage'])) {
            $query->whereHas('myGarage', function ($q) use ($filters) {
                $q->where('brand_id', $filters['brand_in_garage']);
            });
        }

        // 5. Filter by Verification Status (Blue Tick)
        if (isset($filters['is_verified'])) {
            $query->where('verified', $filters['is_verified']);
        }

        // 6. Filter by Role
        if (!empty($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        // 7. Filter by Last Login
        if (!empty($filters['last_login_from'])) {
            $query->where('last_login', '>=', $filters['last_login_from']);
        }
        if (!empty($filters['last_login_to'])) {
            $query->where('last_login', '<=', $filters['last_login_to']);
        }

        // 8. Filter by Date Registered (date_from / date_to if not handled in listings)
        // If no category_id is set, date_from/to apply to user registration date
        if (empty($filters['category_id'])) {
            if (!empty($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }
        }

        // 9. Filter by Gender
        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        // 10. Filter by "Has Points of Interest"
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
