<?php
// app/Services/EmailNotificationService.php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailNotificationService
{
    /**
     * Envoyer une notification à un utilisateur
     */
    public function send(
        User $user,
        string $type,
        array $data = [],
        array $options = []
    ): ?Notification {
        // Vérifier les préférences
        if (!$this->canSendNotification($user, $type)) {
            Log::info("Notification blocked by user preferences", [
                'user_id' => $user->id,
                'type' => $type,
            ]);
            return null;
        }

        // Charger le template
        $template = NotificationTemplate::where('type', $type)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            Log::error("Template not found", ['type' => $type]);
            return null;
        }

        // Render le template
        $rendered = $template->render($data);

        // Créer la notification en base
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $rendered['title'],
            'message' => $rendered['message'],
            'data' => $data,
            'icon' => $options['icon'] ?? $rendered['icon'],
            'color' => $options['color'] ?? $rendered['color'],
            'sound' => $options['sound'] ?? $rendered['sound'],
            'priority' => $options['priority'] ?? 'normal',
            'action_url' => $options['action_url'] ?? null,
            'image_url' => $options['image_url'] ?? null,
            'related_entity_type' => $options['related_entity_type'] ?? null,
            'related_entity_id' => $options['related_entity_id'] ?? null,
        ]);

        // Envoyer l'email
        $this->sendEmail($user, $notification, $template, $data);

        return $notification;
    }

    /**
     * Envoyer l'email
     */
    protected function sendEmail(
        User $user,
        Notification $notification,
        NotificationTemplate $template,
        array $data
    ): void {
        $preferences = $user->notificationPreference;

        // Vérifier si l'email est activé
        if (!$preferences || !$preferences->email_enabled) {
            Log::info("Email disabled for user", ['user_id' => $user->id]);
            return;
        }

        try {
            // Render le template email
            $emailContent = $template->renderEmail($data);

            // Envoyer l'email
            Mail::to($user->email)->send(
                new NotificationMail($notification, $emailContent, $data)
            );

            Log::info("Email notification sent", [
                'user_id' => $user->id,
                'notification_id' => $notification->id,
                'type' => $notification->type,
            ]);

        } catch (\Exception $e) {
            Log::error('Email notification failed', [
                'user_id' => $user->id,
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoyer une notification personnalisée (admin)
     */
    public function sendCustom(
        User $user,
        string $title,
        string $message,
        array $options = []
    ): Notification {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'admin_custom',
            'title' => $title,
            'message' => $message,
            'icon' => $options['icon'] ?? 'announcement',
            'color' => $options['color'] ?? '#f03d24',
            'sound' => $options['sound'] ?? 'default',
            'priority' => $options['priority'] ?? 'normal',
            'action_url' => $options['action_url'] ?? null,
            'image_url' => $options['image_url'] ?? null,
            'is_custom' => true,
            'sent_by_admin' => $options['admin_id'] ?? null,
        ]);

        // Envoyer l'email personnalisé
        if ($user->notificationPreference && $user->notificationPreference->email_enabled) {
            try {
                Mail::to($user->email)->send(
                    new NotificationMail($notification, $message, $options)
                );
            } catch (\Exception $e) {
                Log::error('Custom email notification failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $notification;
    }

    /**
     * Envoyer à plusieurs utilisateurs
     */
    public function sendToMultiple(
        array $userIds,
        string $type,
        array $data = [],
        array $options = []
    ): array {
        $results = [];

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $notification = $this->send($user, $type, $data, $options);
                $results[$userId] = $notification ? 'sent' : 'skipped';
            } else {
                $results[$userId] = 'user_not_found';
            }
        }

        return $results;
    }

    /**
     * Envoyer à tous les utilisateurs (broadcast)
     */
    public function broadcast(
        string $type,
        array $data = [],
        array $filters = []
    ): array {
        $query = User::query();

        // Appliquer les filtres
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if (is_array($value)) {
                    $query->whereIn($key, $value);
                } else {
                    $query->where($key, $value);
                }
            }
        }

        $users = $query->get();
        $results = [
            'total' => $users->count(),
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($users as $user) {
            try {
                $notification = $this->send($user, $type, $data);
                if ($notification) {
                    $results['sent']++;
                } else {
                    $results['skipped']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                Log::error('Broadcast notification failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Envoyer un email de test
     */
    public function testEmail(User $user): Notification
    {
        return $this->sendCustom(
            $user,
            '🔔 تجربة البريد الإلكتروني - DabApp',
            'هذا بريد إلكتروني تجريبي للتحقق من أن نظام الإشعارات يعمل بشكل صحيح. إذا تلقيت هذا البريد الإلكتروني، فكل شيء على ما يرام! ✅',
            [
                'icon' => 'check_circle',
                'color' => '#4CAF50',
                'priority' => 'high',
                'action_url' => config('app.url')
            ]
        );
    }

    /**
     * Vérifier si on peut envoyer
     */
    protected function canSendNotification(User $user, string $type): bool
    {
        $preferences = $user->notificationPreference;

        if (!$preferences) {
            return true; // Par défaut, autoriser
        }

        // Vérifier le canal email
        if (!$preferences->email_enabled) {
            return false;
        }

        // Vérifier le type spécifique
        if (!$preferences->isNotificationEnabled($type)) {
            return false;
        }

        return true;
    }
}
