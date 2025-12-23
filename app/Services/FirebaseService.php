<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Exception\FirebaseException;

class FirebaseService
{
    protected $messaging;
    protected $factory;

    public function __construct()
    {
        $credentialsPath = storage_path('app/' . config('firebase.credentials.file'));

        $this->factory = (new Factory)->withServiceAccount($credentialsPath);
        $this->messaging = $this->factory->createMessaging();
    }

    /**
     * Envoyer une notification à un seul token
     */
    public function sendToToken(string $token, string $title, string $body, array $data = [], array $options = []): array
    {
        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);

            // Configurer pour Android
            if (isset($options['android'])) {
                $message = $message->withAndroidConfig(
                    AndroidConfig::fromArray($options['android'])
                );
            }

            // Configurer pour iOS
            if (isset($options['apns'])) {
                $message = $message->withApnsConfig(
                    ApnsConfig::fromArray($options['apns'])
                );
            }

            $result = $this->messaging->send($message);

            return [
                'success' => true,
                'message_id' => $result,
                'token' => $token,
            ];

        } catch (MessagingException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'token' => $token,
            ];
        } catch (FirebaseException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'token' => $token,
            ];
        }
    }

    /**
     * Envoyer une notification à plusieurs tokens
     */
    public function sendToMultipleTokens(array $tokens, string $title, string $body, array $data = [], array $options = []): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'results' => [],
        ];

        foreach ($tokens as $token) {
            $result = $this->sendToToken($token, $title, $body, $data, $options);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['results'][] = $result;
        }

        return $results;
    }

    /**
     * Envoyer une notification à un topic
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($data);

            $result = $this->messaging->send($message);

            return [
                'success' => true,
                'message_id' => $result,
                'topic' => $topic,
            ];

        } catch (MessagingException | FirebaseException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'topic' => $topic,
            ];
        }
    }

    /**
     * Souscrire des tokens à un topic
     */
    public function subscribeToTopic(array $tokens, string $topic): array
    {
        try {
            $result = $this->messaging->subscribeToTopic($topic, $tokens);

            return [
                'success' => true,
                'success_count' => $result->successes()->count(),
                'failure_count' => $result->failures()->count(),
                'errors' => $result->failures(),
            ];

        } catch (MessagingException | FirebaseException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Désouscrire des tokens d'un topic
     */
    public function unsubscribeFromTopic(array $tokens, string $topic): array
    {
        try {
            $result = $this->messaging->unsubscribeFromTopic($topic, $tokens);

            return [
                'success' => true,
                'success_count' => $result->successes()->count(),
                'failure_count' => $result->failures()->count(),
            ];

        } catch (MessagingException | FirebaseException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Valider un token FCM
     */
    public function validateToken(string $token): bool
    {
        try {
            $this->messaging->validate($token);
            return true;
        } catch (MessagingException | FirebaseException $e) {
            return false;
        }
    }
}
