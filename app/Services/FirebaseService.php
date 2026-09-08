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
        // Lazy initialization
    }

    protected function initialize()
    {
        if ($this->messaging) {
            return;
        }

        try {
            $credentialsFile = config('firebase.credentials.file');
            $credentialsPath = $credentialsFile ? storage_path('app/' . $credentialsFile) : null;

            // Fallback locations
            if (!$credentialsPath || !file_exists($credentialsPath) || is_dir($credentialsPath)) {
                $fallbackPaths = [
                    base_path('app/firebase_credentials.json'),
                    base_path('firebase_credentials.json'),
                ];

                reset($fallbackPaths);
                $found = false;
                foreach ($fallbackPaths as $path) {
                    if (file_exists($path) && !is_dir($path)) {
                        $credentialsPath = $path;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    throw new \Exception("Firebase credentials file not found or invalid at: " . ($credentialsPath ?? 'none'));
                }
            }

            $this->factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->messaging = $this->factory->createMessaging();
        } catch (\Exception $e) {
            \Log::error("Firebase Initialization Failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Envoyer une notification à un seul token
     */
    public function sendToToken(string $token, string $title, string $body, array $data = [], array $options = []): array
    {
        try {
            $this->initialize();
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
        } catch (\Exception $e) { // Catch general exceptions from initialize
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
     * Send a batch of individually-targeted messages in as few FCM HTTP calls as
     * possible. FCM caps a batch request at 500 messages, so a larger list is
     * split into 500-message calls to `Messaging::sendAll()`.
     *
     * Each $item: [
     *   'token'   => string,                 // FCM registration token
     *   'title'   => string,
     *   'body'    => string,
     *   'data'    => array<string,string>,   // FCM requires string values
     *   'android' => array|null,             // AndroidConfig::fromArray shape
     *   'apns'    => array|null,             // ApnsConfig::fromArray shape
     * ]
     *
     * @return array{
     *   sent: int,
     *   failed: int,
     *   invalid_tokens: string[],            // permanently dead — caller should deactivate
     *   results: array<string,bool>          // fcm_token => delivered?
     * }
     */
    public function sendBatch(array $items): array
    {
        $out = ['sent' => 0, 'failed' => 0, 'invalid_tokens' => [], 'results' => []];

        if (empty($items)) {
            return $out;
        }

        try {
            $this->initialize();
        } catch (\Throwable $e) {
            // Credentials/init failure — nothing can be delivered.
            foreach ($items as $item) {
                $out['results'][$item['token']] = false;
                $out['failed']++;
            }
            return $out;
        }

        foreach (array_chunk($items, 500) as $chunk) {
            $messages = [];
            foreach ($chunk as $item) {
                $message = CloudMessage::withTarget('token', $item['token'])
                    ->withNotification(Notification::create($item['title'], $item['body']))
                    ->withData($item['data'] ?? []);

                if (!empty($item['android'])) {
                    $message = $message->withAndroidConfig(AndroidConfig::fromArray($item['android']));
                }
                if (!empty($item['apns'])) {
                    $message = $message->withApnsConfig(ApnsConfig::fromArray($item['apns']));
                }

                $messages[] = $message;
            }

            try {
                $report = $this->messaging->sendAll($messages);
            } catch (\Throwable $e) {
                // Whole-chunk failure (network, auth, quota) — count every token as failed
                // but keep going with the remaining chunks.
                \Log::error('FirebaseService::sendBatch chunk failed: ' . $e->getMessage());
                foreach ($chunk as $item) {
                    $out['results'][$item['token']] = false;
                    $out['failed']++;
                }
                continue;
            }

            foreach ($report->getItems() as $sendReport) {
                $token = $sendReport->target()->value();

                if ($sendReport->isSuccess()) {
                    $out['results'][$token] = true;
                    $out['sent']++;
                    continue;
                }

                $out['results'][$token] = false;
                $out['failed']++;

                if ($sendReport->messageTargetWasInvalid() || $sendReport->messageWasSentToUnknownToken()) {
                    $out['invalid_tokens'][] = $token;
                }
            }
        }

        return $out;
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
            $this->initialize();
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
            $this->initialize();
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
            $this->initialize();
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
            $this->initialize();
            $this->messaging->validate($token);
            return true;
        } catch (\Exception $e) {
            return false;
        } catch (MessagingException | FirebaseException $e) {
            return false;
        }
    }
}
