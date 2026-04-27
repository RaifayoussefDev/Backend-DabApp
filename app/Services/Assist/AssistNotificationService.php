<?php

namespace App\Services\Assist;

use App\Models\User;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\AssistNotification;

class AssistNotificationService
{
    private const TEMPLATES = [
        'new_request' => [
            'title' => 'New assistance request nearby',
            'body'  => 'A rider needs help with %s near you.',
        ],
        'accepted' => [
            'title' => 'Helper is on the way!',
            'body'  => 'Your request has been accepted. Help is coming.',
        ],
        'en_route' => [
            'title' => 'Helper is en route',
            'body'  => 'Your helper is heading to your location.',
        ],
        'arrived' => [
            'title' => 'Helper has arrived',
            'body'  => 'Your helper has arrived at your location.',
        ],
        'completed' => [
            'title' => 'Assistance completed',
            'body'  => 'Your assistance request has been completed. Please rate your experience.',
        ],
        'cancelled' => [
            'title' => 'Request cancelled',
            'body'  => 'The assistance request has been cancelled by the rider.',
        ],
        'helper_cancelled' => [
            'title' => 'Helper cancelled',
            'body'  => 'Your helper cancelled the mission. We are looking for a new helper.',
        ],
        'rated' => [
            'title' => 'You received a new rating',
            'body'  => 'Someone rated your assistance. Check your profile.',
        ],
        'seeker_finished' => [
            'title' => 'Mission confirmed by rider',
            'body'  => 'The rider confirmed the assistance is complete. Great work!',
        ],
    ];

    /**
     * Persist an in-app notification and prepare the FCM payload.
     */
    public function notify(User $user, string $type, AssistanceRequest $request): AssistNotification
    {
        $template = self::TEMPLATES[$type] ?? [
            'title' => 'Velocity Assist',
            'body'  => 'You have a new notification.',
        ];

        $expertiseName = $request->expertiseTypes?->first()?->name ?? 'assistance';

        $notification = AssistNotification::create([
            'user_id'    => $user->id,
            'request_id' => $request->id,
            'type'       => $type,
            'title'      => $template['title'],
            'body'       => sprintf($template['body'], $expertiseName),
            'is_read'    => false,
        ]);

        // TODO: FCM — send push notification via Firebase Cloud Messaging
        // $this->sendFcmPush($user, $notification);

        return $notification;
    }

    // TODO: FCM — implement Firebase push delivery
    // private function sendFcmPush(User $user, AssistNotification $notification): void
    // {
    //     // Retrieve FCM token from notification_tokens table
    //     // Build FCM payload and dispatch via kreait/firebase-php
    // }
}
