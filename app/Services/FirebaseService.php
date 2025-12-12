<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Exception\MessagingException;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        try {
            // Initialiser Firebase
            $factory = (new Factory)->withServiceAccount(config('firebase.credentials'));
            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error("Firebase initialization error: " . $e->getMessage());
            $this->messaging = null;
        }
    }

    /**
     * Envoyer une notification push à un seul device
     */
    public function sendToDevice(string $fcmToken, array $data)
    {
        if (!$this->messaging) {
            Log::error("Firebase messaging not initialized");
            return [
                'success' => false,
                'error' => 'Firebase not configured',
            ];
        }

        try {
            // Créer la notification Firebase
            $notification = FirebaseNotification::create(
                $data['title'],
                $data['message']
            );

            // Créer le message
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification($notification)
                ->withData($data['data'] ?? []);

            // Configuration Android
            if (isset($data['android_config'])) {
                $message = $message->withAndroidConfig(
                    AndroidConfig::fromArray($data['android_config'])
                );
            } else {
                // Config Android par défaut
                $message = $message->withAndroidConfig(
                    AndroidConfig::fromArray([
                        'priority' => $data['priority'] ?? 'high',
                        'notification' => [
                            'sound' => $data['sound'] ?? 'default',
                            'color' => $data['color'] ?? '#FF0000',
                            'icon' => $data['icon'] ?? 'ic_notification',
                        ],
                    ])
                );
            }

            // Configuration iOS
            if (isset($data['apns_config'])) {
                $message = $message->withApnsConfig(
                    ApnsConfig::fromArray($data['apns_config'])
                );
            } else {
                // Config iOS par défaut
                $message = $message->withApnsConfig(
                    ApnsConfig::fromArray([
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'alert' => [
                                    'title' => $data['title'],
                                    'body' => $data['message'],
                                ],
                                'sound' => $data['sound'] ?? 'default',
                                'badge' => $data['badge'] ?? 1,
                            ],
                        ],
                    ])
                );
            }

            // Envoyer
            $result = $this->messaging->send($message);

            Log::info("Firebase push sent successfully", [
                'fcm_token' => substr($fcmToken, 0, 20) . '...',
                'message_id' => $result,
            ]);

            return [
                'success' => true,
                'message_id' => $result,
            ];

        } catch (MessagingException $e) {
            Log::error("Firebase messaging error: " . $e->getMessage(), [
                'fcm_token' => substr($fcmToken, 0, 20) . '...',
                'error_code' => $e->getCode(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];

        } catch (\Exception $e) {
            Log::error("Firebase send error: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Envoyer à plusieurs devices
     */
    public function sendToMultipleDevices(array $fcmTokens, array $data)
    {
        if (!$this->messaging) {
            Log::error("Firebase messaging not initialized");
            return [
                'success' => false,
                'error' => 'Firebase not configured',
            ];
        }

        $results = [
            'success' => 0,
            'failure' => 0,
            'details' => [],
        ];

        foreach ($fcmTokens as $token) {
            $result = $this->sendToDevice($token, $data);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failure']++;
            }

            $results['details'][] = [
                'token' => substr($token, 0, 20) . '...',
                'result' => $result,
            ];
        }

        return $results;
    }

    /**
     * Envoyer à un topic (groupe)
     */
    public function sendToTopic(string $topic, array $data)
    {
        if (!$this->messaging) {
            Log::error("Firebase messaging not initialized");
            return [
                'success' => false,
                'error' => 'Firebase not configured',
            ];
        }

        try {
            $notification = FirebaseNotification::create(
                $data['title'],
                $data['message']
            );

            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($data['data'] ?? []);

            $result = $this->messaging->send($message);

            Log::info("Firebase topic message sent", [
                'topic' => $topic,
                'message_id' => $result,
            ]);

            return [
                'success' => true,
                'message_id' => $result,
            ];

        } catch (\Exception $e) {
            Log::error("Firebase topic send error: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Valider un token FCM
     */
    public function validateToken(string $fcmToken): bool
    {
        try {
            // Essayer d'envoyer un message de test (dry run)
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification(
                    FirebaseNotification::create('Test', 'Validation')
                );

            $this->messaging->validate($message);

            return true;

        } catch (\Exception $e) {
            Log::warning("Invalid FCM token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Subscribe un token à un topic
     */
    public function subscribeToTopic(string $fcmToken, string $topic)
    {
        try {
            $this->messaging->subscribeToTopic($topic, $fcmToken);

            Log::info("Token subscribed to topic", [
                'token' => substr($fcmToken, 0, 20) . '...',
                'topic' => $topic,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Subscribe to topic error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unsubscribe un token d'un topic
     */
    public function unsubscribeFromTopic(string $fcmToken, string $topic)
    {
        try {
            $this->messaging->unsubscribeFromTopic($topic, $fcmToken);

            Log::info("Token unsubscribed from topic", [
                'token' => substr($fcmToken, 0, 20) . '...',
                'topic' => $topic,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Unsubscribe from topic error: " . $e->getMessage());
            return false;
        }
    }
}
