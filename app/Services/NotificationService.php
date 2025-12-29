<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationToken;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

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

        // ✅ Déterminer la langue de l'utilisateur (défaut: 'en')
        $language = $user->language ?? 'en';

        // ✅ Rendre le template dans la langue de l'utilisateur
        $rendered = $template->render($data, $language);

        // Extraire les informations d'entité depuis les options ou data
        $entityInfo = $this->extractEntityInfo($data, $options);

        // Créer la notification en BDD
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $rendered['title'],
            'message' => $rendered['message'],
            'data' => $data,
            'related_entity_type' => $entityInfo['entity_type'],
            'related_entity_id' => $entityInfo['entity_id'],
            'action_url' => $entityInfo['action_url'],
            'icon' => $rendered['icon'] ?? null,
            'color' => $rendered['color'] ?? null,
            'sound' => $rendered['sound'] ?? 'default',
            'priority' => $options['priority'] ?? 'normal',
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
     * Extraire les informations d'entité depuis les données
     */
    protected function extractEntityInfo(array $data, array $options): array
    {
        $entityInfo = [
            'entity_type' => null,
            'entity_id' => null,
            'action_url' => null,
        ];

        // Si on a passé directement un model
        if (isset($options['entity']) && $options['entity'] instanceof Model) {
            $entityInfo['entity_type'] = get_class($options['entity']);
            $entityInfo['entity_id'] = $options['entity']->id;
        }
        // Si on a passé entity_type et entity_id manuellement
        elseif (isset($data['entity_type']) && isset($data['entity_id'])) {
            $entityInfo['entity_type'] = $data['entity_type'];
            $entityInfo['entity_id'] = $data['entity_id'];
        }
        // Détection automatique selon les clés présentes
        else {
            if (isset($data['listing_id'])) {
                $entityInfo['entity_type'] = 'App\Models\Listing';
                $entityInfo['entity_id'] = $data['listing_id'];
            } elseif (isset($data['soom_id'])) {
                $entityInfo['entity_type'] = 'App\Models\Soom';
                $entityInfo['entity_id'] = $data['soom_id'];
            } elseif (isset($data['order_id'])) {
                $entityInfo['entity_type'] = 'App\Models\Order';
                $entityInfo['entity_id'] = $data['order_id'];
            } elseif (isset($data['event_id'])) {
                $entityInfo['entity_type'] = 'App\Models\Event';
                $entityInfo['entity_id'] = $data['event_id'];
            } elseif (isset($data['poi_id'])) {
                $entityInfo['entity_type'] = 'App\Models\Poi';
                $entityInfo['entity_id'] = $data['poi_id'];
            } elseif (isset($data['guide_id'])) {
                $entityInfo['entity_type'] = 'App\Models\Guide';
                $entityInfo['entity_id'] = $data['guide_id'];
            }
        }

        // Action URL personnalisée ou auto-générée
        $entityInfo['action_url'] = $data['action_url'] ?? $options['action_url'] ?? null;

        return $entityInfo;
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
                'entity_type' => $notification->related_entity_type,
                'entity_id' => (string) $notification->related_entity_id,
                'action_url' => $notification->action_url ?? $notification->action_route,
            ];

            // Fusionner avec les data additionnelles (sans écraser les clés importantes)
            if ($notification->data) {
                $pushData = array_merge($notification->data, $pushData);
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
                $messageId = isset($result['message_id']) && is_array($result['message_id'])
                    ? json_encode($result['message_id'])
                    : ($result['message_id'] ?? null);
                $log->markAsSent($messageId);
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
        // Extraire les informations d'entité
        $entityInfo = $this->extractEntityInfo($data, $options);

        // Créer la notification
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'admin_custom',
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'related_entity_type' => $entityInfo['entity_type'],
            'related_entity_id' => $entityInfo['entity_id'],
            'action_url' => $entityInfo['action_url'],
            'icon' => $options['icon'] ?? 'notifications',
            'color' => $options['color'] ?? '#FF6B6B',
            'sound' => $options['sound'] ?? 'default',
            'priority' => $options['priority'] ?? 'normal',
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

    // ==================== LISTINGS ====================

    /**
     * Notification: Nouveau listing créé
     */
    public function notifyListingCreated(User $user, $listing): array
    {
        return $this->sendToUser($user, 'listing_created', [
            'listing_id' => $listing->id,
            'listing_title' => $listing->title,
            'listing_price' => $listing->price,
            'seller_name' => $listing->user->full_name ?? 'Vendeur',
        ], [
            'entity' => $listing,
            'priority' => 'normal',
        ]);
    }

    /**
     * Notification: Listing approuvé
     */
    public function notifyListingApproved(User $user, $listing): array
    {
        return $this->sendToUser($user, 'listing_approved', [
            'listing_id' => $listing->id,
            'listing_title' => $listing->title,
        ], [
            'entity' => $listing,
            'priority' => 'high',
        ]);
    }

    /**
     * Notification: Listing rejeté
     */
    public function notifyListingRejected(User $user, $listing, string $reason = null): array
    {
        return $this->sendToUser($user, 'listing_rejected', [
            'listing_id' => $listing->id,
            'listing_title' => $listing->title,
            'rejection_reason' => $reason,
        ], [
            'entity' => $listing,
            'priority' => 'high',
        ]);
    }

    /**
     * Notification: Nouveau message sur un listing
     */
    public function notifyListingMessage(User $user, $listing, $sender): array
    {
        return $this->sendToUser($user, 'listing_message', [
            'listing_id' => $listing->id,
            'listing_title' => $listing->title,
            'sender_name' => $sender->full_name,
            'sender_id' => $sender->id,
        ], [
            'entity' => $listing,
            'priority' => 'high',
        ]);
    }

    /**
     * Notification: Listing vendu
     */
    public function notifyListingSold(User $user, $listing): array
    {
        return $this->sendToUser($user, 'listing_sold', [
            'listing_id' => $listing->id,
            'listing_title' => $listing->title,
        ], [
            'entity' => $listing,
            'priority' => 'high',
        ]);
    }

    // ==================== SOOM (ENCHÈRES) ====================

    /**
     * Notification: Nouvelle enchère placée
     */
    public function notifySoomBid(User $user, $soom, $bidAmount, $bidder = null): array
    {
        return $this->sendToUser($user, 'soom_bid', [
            'soom_id' => $soom->id,
            'soom_title' => $soom->title,
            'bid_amount' => $bidAmount,
            'bidder_name' => $bidder ? $bidder->full_name : 'Un enchérisseur',
        ], [
            'entity' => $soom,
            'priority' => 'high',
        ]);
    }

    /**
     * Notification: Dépassé dans une enchère
     */
    public function notifySoomOutbid(User $user, $soom, $newBidAmount): array
    {
        return $this->sendToUser($user, 'soom_outbid', [
            'soom_id' => $soom->id,
            'soom_title' => $soom->title,
            'new_bid_amount' => $newBidAmount,
        ], [
            'entity' => $soom,
            'priority' => 'high',
        ]);
    }

    /**
     * Notification: Enchère gagnée
     */
    public function notifySoomWon(User $user, $soom, $winningBid): array
    {
        return $this->sendToUser($user, 'soom_won', [
            'soom_id' => $soom->id,
            'soom_title' => $soom->title,
            'winning_bid' => $winningBid,
        ], [
            'entity' => $soom,
            'priority' => 'urgent',
        ]);
    }

    /**
     * Notification: Enchère perdue
     */
    public function notifySoomLost(User $user, $soom): array
    {
        return $this->sendToUser($user, 'soom_lost', [
            'soom_id' => $soom->id,
            'soom_title' => $soom->title,
        ], [
            'entity' => $soom,
            'priority' => 'normal',
        ]);
    }

    /**
     * Notification: Enchère se termine bientôt (1h)
     */
    public function notifySoomEndingSoon(User $user, $soom): array
    {
        return $this->sendToUser($user, 'soom_ending_soon', [
            'soom_id' => $soom->id,
            'soom_title' => $soom->title,
            'ends_at' => $soom->ends_at->toIso8601String(),
        ], [
            'entity' => $soom,
            'priority' => 'high',
        ]);
    }

    // ==================== ORDERS (COMMANDES) ====================

    /**
     * Notification: Nouvelle commande créée
     */
    public function notifyOrderCreated(User $user, $order): array
    {
        return $this->sendToUser($user, 'order_created', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total_amount' => $order->total_amount,
        ], [
            'entity' => $order,
            'priority' => 'high',
        ]);
    }

    /**
     * Notification: Statut de commande changé
     */
    public function notifyOrderStatusChanged(User $user, $order, string $newStatus): array
    {
        return $this->sendToUser($user, 'order_status_' . $newStatus, [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'new_status' => $newStatus,
            'status_label' => $this->getOrderStatusLabel($newStatus),
        ], [
            'entity' => $order,
            'priority' => 'high',
        ]);
    }

    /**
     * Notification: Commande confirmée
     */
    public function notifyOrderConfirmed(User $user, $order): array
    {
        return $this->notifyOrderStatusChanged($user, $order, 'confirmed');
    }

    /**
     * Notification: Commande expédiée
     */
    public function notifyOrderShipped(User $user, $order, string $trackingNumber = null): array
    {
        return $this->sendToUser($user, 'order_shipped', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'tracking_number' => $trackingNumber,
        ], [
            'entity' => $order,
            'priority' => 'high',
        ]);
    }

    /**
     * Notification: Commande livrée
     */
    public function notifyOrderDelivered(User $user, $order): array
    {
        return $this->notifyOrderStatusChanged($user, $order, 'delivered');
    }

    /**
     * Notification: Commande annulée
     */
    public function notifyOrderCancelled(User $user, $order, string $reason = null): array
    {
        return $this->sendToUser($user, 'order_cancelled', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'cancellation_reason' => $reason,
        ], [
            'entity' => $order,
            'priority' => 'high',
        ]);
    }

    // ==================== PAYMENT (PAIEMENTS) ====================

    /**
     * Notification: Paiement réussi
     */
    public function notifyPaymentSuccess(User $user, $payment, $order = null): array
    {
        $data = [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
        ];

        $options = ['priority' => 'high'];

        if ($order) {
            $data['order_id'] = $order->id;
            $data['order_number'] = $order->order_number;
            $options['entity'] = $order;
        }

        return $this->sendToUser($user, 'payment_success', $data, $options);
    }

    /**
     * Notification: Paiement échoué
     */
    public function notifyPaymentFailed(User $user, $payment, string $reason = null): array
    {
        return $this->sendToUser($user, 'payment_failed', [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'failure_reason' => $reason,
        ], [
            'priority' => 'urgent',
        ]);
    }

    /**
     * Notification: Remboursement traité
     */
    public function notifyRefundProcessed(User $user, $refund, $order = null): array
    {
        $data = [
            'refund_id' => $refund->id,
            'amount' => $refund->amount,
        ];

        $options = ['priority' => 'high'];

        if ($order) {
            $data['order_id'] = $order->id;
            $data['order_number'] = $order->order_number;
            $options['entity'] = $order;
        }

        return $this->sendToUser($user, 'refund_processed', $data, $options);
    }

    // ==================== EVENTS (ÉVÉNEMENTS) ====================

    /**
     * Notification: Nouvel événement créé
     */
    public function notifyEventCreated(User $user, $event): array
    {
        return $this->sendToUser($user, 'event_created', [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'event_date' => $event->start_date->toIso8601String(),
            'event_location' => $event->location,
        ], [
            'entity' => $event,
            'priority' => 'normal',
        ]);
    }

    /**
     * Notification: Rappel d'événement (24h avant)
     */
    public function notifyEventReminder(User $user, $event): array
    {
        return $this->sendToUser($user, 'event_reminder', [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'event_date' => $event->start_date->toIso8601String(),
            'event_location' => $event->location,
        ], [
            'entity' => $event,
            'priority' => 'high',
        ]);
    }

    /**
     * Notification: Événement commence bientôt (1h)
     */
    public function notifyEventStartingSoon(User $user, $event): array
    {
        return $this->sendToUser($user, 'event_starting_soon', [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'starts_at' => $event->start_date->toIso8601String(),
        ], [
            'entity' => $event,
            'priority' => 'high',
        ]);
    }

    /**
     * Notification: Événement annulé
     */
    public function notifyEventCancelled(User $user, $event, string $reason = null): array
    {
        return $this->sendToUser($user, 'event_cancelled', [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'cancellation_reason' => $reason,
        ], [
            'entity' => $event,
            'priority' => 'urgent',
        ]);
    }

    /**
     * Notification: Modification d'événement
     */
    public function notifyEventUpdated(User $user, $event): array
    {
        return $this->sendToUser($user, 'event_updated', [
            'event_id' => $event->id,
            'event_title' => $event->title,
        ], [
            'entity' => $event,
            'priority' => 'normal',
        ]);
    }

    // ==================== POI (POINTS D'INTÉRÊT) ====================

    /**
     * Notification: Nouveau POI à proximité
     */
    public function notifyNearbyPoi(User $user, $poi, float $distance): array
    {
        return $this->sendToUser($user, 'poi_nearby', [
            'poi_id' => $poi->id,
            'poi_name' => $poi->name,
            'poi_type' => $poi->type,
            'distance' => round($distance, 2),
        ], [
            'entity' => $poi,
            'priority' => 'normal',
        ]);
    }

    // ==================== HELPERS ====================

    /**
     * Traduire le statut de commande
     */
    protected function getOrderStatusLabel(string $status): string
    {
        $labels = [
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'processing' => 'En préparation',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée',
            'refunded' => 'Remboursée',
        ];

        return $labels[$status] ?? $status;
    }

    public function notifyListingUpdated(User $user, $listing): array
    {
        return $this->sendToUser($user, 'listing_updated', [
            'listing_id' => $listing->id,
            'listing_title' => $listing->title,
            'listing_price' => $listing->price,
            'updated_at' => $listing->updated_at->toIso8601String(),
        ], [
            'entity' => $listing,
            'priority' => 'normal',
        ]);
    }
}
