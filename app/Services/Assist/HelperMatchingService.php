<?php

namespace App\Services\Assist;

use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\HelperProfile;
use Illuminate\Database\Eloquent\Collection;

class HelperMatchingService
{
    public function __construct(
        private readonly AssistNotificationService $notificationService
    ) {}

    /**
     * Find nearby, available, verified helpers with the required expertise,
     * notify each of them, and return the collection.
     */
    public function findNearby(AssistanceRequest $request): Collection
    {
        $helpers = HelperProfile::available()
            ->nearTo((float) $request->latitude, (float) $request->longitude)
            ->whereHas('expertiseTypes', function ($q) use ($request) {
                $q->where('expertise_types.id', $request->expertise_type_id);
            })
            ->with('user')
            ->get()
            ->filter(fn($h) => isset($h->distance_km) && $h->distance_km <= $h->service_radius_km);

        foreach ($helpers as $helperProfile) {
            $this->notificationService->notify(
                $helperProfile->user,
                'new_request',
                $request
            );
        }

        return $helpers;
    }
}
