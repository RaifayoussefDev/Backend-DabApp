<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationToken;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Envoyer une notification à un utilisateur
     */
    public function sendToUser(User $user, string $type, array $data = [], array $options = []): array
    {
        // Vérifier les préférences
        $preferences = $user->notificationPreference;

        if (!$preferences || !$preferences->isNotificationEnabled($type)) {
            return [
                'success' => false,
                'message' => 'Notification disabled for this type',
            ];
        }

        // Récupérer le template
        $template = NotificationTemplate::where('type', $type)->where('is_active', true)->first();

        if (!$template) {
            return [
                'success' => false,
                'message' => 'Template not found',
            ];
        }

        // Rendre le template
        $rendered = $template->render($data);

        // Créer la notification en BDD
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $rendered['title'],
            'message' => $rendered['message'],
            'data' => $data,
            'icon' => $rendered['icon'] ?? null,
            'color' => $rendered['color'] ?? null,
            'sound' => $rendered['sound'] ?? 'default',
            'priority' => $options['priority'] ?? 'normal',
            'action_url' => $data['action_url'] ?? null,
            'image_url' => $data['image_url'] ?? null,
        ]);

        // Envoyer le push si activé
        $pushResults = [];
        if ($preferences->canSendPush()) {
            $pushResults = $this->sendPushNotification($user, $notification, $options);
        }

        return [
            'success' => true,
            'notification_id' => $notification->id,
            'push_results' => $pushResults,
        ];
    }

    /**
     * Envoyer une notification push
     */
    protected function sendPushNotification(User $user, Notification $notification, array $options = []): array
    {
        $tokens = NotificationToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        if ($tokens->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No active tokens found',
            ];
        }

        $results = [
            'total' => $tokens->count(),
            'sent' => 0,
            'failed' => 0,
        ];

        foreach ($tokens as $token) {
            $pushData = [
                'notification_id' => (string) $notification->id,
                'type' => $notification->type,
                'action_url' => $notification->action_url,
            ];

            // Fusionner avec les data additionnelles
            if ($notification->data) {
                $pushData = array_merge($pushData, $notification->data);
            }

            // Options spécifiques par plateforme
            $fcmOptions = $this->buildPlatformOptions($token->device_type, $notification, $options);

            $result = $this->firebase->sendToToken(
                $token->fcm_token,
                $notification->title,
                $notification->message,
                $pushData,
                $fcmOptions
            );

            // Logger le résultat
            $log = NotificationLog::create([
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'channel' => 'push',
                'fcm_token' => $token->fcm_token,
                'device_type' => $token->device_type,
                'device_id' => $token->device_id,
                'status' => $result['success'] ? 'sent' : 'failed',
                'fcm_message_id' => isset($result['message_id']) && is_array($result['message_id'])
                    ? json_encode($result['message_id'])
                    : ($result['message_id'] ?? null),
                'error_message' => isset($result['error']) && is_array($result['error'])
                    ? json_encode($result['error'])
                    : ($result['error'] ?? null),
                'queued_at' => now(),
            ]);

            if ($result['success']) {
                $results['sent']++;
                $token->updateLastUsed();
                $token->resetFailedAttempts();
                $log->markAsSent($result['message_id']);
            } else {
                $results['failed']++;
                $token->incrementFailedAttempts();
                $log->markAsFailed($result['error']);
            }
        }

        // Mettre à jour la notification
        if ($results['sent'] > 0) {
            $notification->markPushSent();
        }

        return $results;
    }

    /**
     * Construire les options spécifiques à chaque plateforme
     */
    protected function buildPlatformOptions(string $deviceType, Notification $notification, array $options = []): array
    {
        $config = [];

        // Configuration Android
        if ($deviceType === 'android') {
            $config['android'] = [
                'priority' => $notification->priority === 'high' ? 'high' : 'normal',
                'notification' => [
                    'title' => $notification->title,
                    'body' => $notification->message,
                    'sound' => $notification->sound ?? 'default',
                    'color' => $notification->color ?? '#FF6B6B',
                    'icon' => $notification->icon ?? 'ic_notification',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ];

            if ($notification->image_url) {
                $config['android']['notification']['image'] = $notification->image_url;
            }
        }

        // Configuration iOS
        if ($deviceType === 'ios') {
            $config['apns'] = [
                'headers' => [
                    'apns-priority' => $notification->priority === 'high' ? '10' : '5',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $notification->title,
                            'body' => $notification->message,
                        ],
                        'sound' => $notification->sound ?? 'default',
                        'badge' => 1,
                        'mutable-content' => 1,
                    ],
                ],
            ];

            if ($notification->image_url) {
                $config['apns']['payload']['aps']['mutable-content'] = 1;
                $config['apns']['fcm_options'] = [
                    'image' => $notification->image_url,
                ];
            }
        }

        return $config;
    }

    /**
     * Envoyer une notification à plusieurs utilisateurs
     */
    public function sendToMultipleUsers(array $userIds, string $type, array $data = [], array $options = []): array
    {
        $results = [
            'total' => count($userIds),
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($userIds as $userId) {
            $user = User::find($userId);

            if (!$user) {
                $results['failed']++;
                continue;
            }

            $result = $this->sendToUser($user, $type, $data, $options);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = [
                'user_id' => $userId,
                'result' => $result,
            ];
        }

        return $results;
    }

    /**
     * Envoyer une notification personnalisée (admin)
     */
    public function sendCustomNotification(User $user, string $title, string $message, array $data = [], array $options = []): array
    {
        // Créer la notification
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'admin_custom',
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'icon' => $options['icon'] ?? 'notifications',
            'color' => $options['color'] ?? '#FF6B6B',
            'sound' => $options['sound'] ?? 'default',
            'priority' => $options['priority'] ?? 'normal',
            'action_url' => $data['action_url'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'is_custom' => true,
            'sent_by_admin' => auth()->id(),
        ]);

        // Envoyer le push
        $preferences = $user->notificationPreference;
        $pushResults = [];

        if ($preferences && $preferences->canSendPush()) {
            $pushResults = $this->sendPushNotification($user, $notification, $options);
        }

        return [
            'success' => true,
            'notification_id' => $notification->id,
            'push_results' => $pushResults,
        ];
    }
}
