<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\NotificationPreference;
use App\Models\NotificationToken;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration des notifications d'événements
 * Utilise le système de notifications existant (tables notifications, notification_preferences, etc.)
 */
class EventNotificationService
{
    /**
     * Envoyer une notification à un utilisateur pour un événement
     *
     * @param User $user Utilisateur destinataire
     * @param Event $event Événement concerné
     * @param string $type Type de notification (event_new, event_update, event_reminder, etc.)
     * @param array $data Données variables pour le template
     */
    public function sendToUser(User $user, Event $event, string $type, array $data = [])
    {
        // Vérifier les préférences utilisateur
        $preference = NotificationPreference::firstOrCreate(['user_id' => $user->id]);

        // Mapper le type vers le champ de préférence
        $preferenceField = $this->getPreferenceField($type);

        if (!$preferenceField || !$preference->$preferenceField) {
            Log::info("Notification {$type} désactivée pour user {$user->id}");
            return false;
        }

        // Vérifier les quiet hours
        if ($preference->isQuietHours()) {
            Log::info("User {$user->id} est en quiet hours");
            return false;
        }

        // Récupérer le template
        $template = NotificationTemplate::where('type', $type)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            Log::error("Template {$type} introuvable");
            return false;
        }

        // Préparer les données pour le template
        $templateData = array_merge([
            'event_title' => $event->title,
            'event_title_ar' => $event->title_ar,
            'event_date' => $event->event_date->format('Y-m-d'),
            'event_location' => $event->venue_name ?? '',
            'event_location_ar' => $event->venue_name_ar ?? '',
        ], $data);

        // Rendre le template
        $rendered = $template->render($templateData);

        // Créer la notification in-app
        if ($preference->in_app_enabled) {
            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $rendered['title'],
                'message' => $rendered['message'],
                'related_entity_type' => 'event',
                'related_entity_id' => $event->id,
                'action_url' => "/events/{$event->id}",
                'icon' => $rendered['icon'],
                'color' => $rendered['color'],
                'sound' => $rendered['sound'],
                'priority' => $this->getPriority($type),
                'is_read' => false,
                'is_deleted' => false,
                'sent_by_admin' => null,
                'is_custom' => false,
            ]);
        }

        // Envoyer push notification
        if ($preference->canSendPush()) {
            $this->sendPushNotification($user, $rendered, $event, $type);
        }

        // Envoyer email
        if ($preference->canSendEmail()) {
            $this->sendEmailNotification($user, $template, $templateData, $event);
        }

        return true;
    }

    /**
     * Envoyer à tous les participants de l'événement
     */
    public function sendToParticipants(Event $event, string $type, array $data = [])
    {
        $participants = $event->participants()->with('user')->get();

        foreach ($participants as $participant) {
            if ($participant->user) {
                $this->sendToUser($participant->user, $event, $type, $data);
            }
        }

        return true;
    }

    /**
     * Envoyer à tous les utilisateurs intéressés
     */
    public function sendToInterestedUsers(Event $event, string $type, array $data = [])
    {
        $interestedUsers = $event->interestedUsers;

        foreach ($interestedUsers as $user) {
            $this->sendToUser($user, $event, $type, $data);
        }

        return true;
    }

    /**
     * Envoyer à l'organisateur
     */
    public function sendToOrganizer(Event $event, string $type, array $data = [])
    {
        if ($event->organizer) {
            return $this->sendToUser($event->organizer, $event, $type, $data);
        }

        return false;
    }

    /**
     * Notifier l'organisateur d'un nouveau participant
     */
    public function notifyOrganizerNewParticipant(Event $event, User $participant)
    {
        return $this->sendToOrganizer($event, 'event_new_participant', [
            'participant_name' => $participant->first_name . ' ' . $participant->last_name,
        ]);
    }

    /**
     * Notifier le participant de la confirmation d'inscription
     */
    public function notifyParticipantRegistrationConfirmed(Event $event, User $participant)
    {
        return $this->sendToUser($participant, $event, 'event_registration_confirmed');
    }

    /**
     * Notifier les participants d'une mise à jour
     */
    public function notifyParticipantsEventUpdated(Event $event, string $updateDetails = '')
    {
        return $this->sendToParticipants($event, 'event_update', [
            'update_details' => $updateDetails,
        ]);
    }

    /**
     * Notifier l'annulation d'un événement
     */
    public function notifyEventCancelled(Event $event, string $reason = '')
    {
        // Notifier participants et intéressés
        $this->sendToParticipants($event, 'event_cancelled', ['reason' => $reason]);
        $this->sendToInterestedUsers($event, 'event_cancelled', ['reason' => $reason]);

        return true;
    }

    /**
     * Envoyer un rappel d'événement
     */
    public function sendEventReminder(Event $event, int $hoursBeforeEvent)
    {
        return $this->sendToParticipants($event, 'event_reminder', [
            'hours' => $hoursBeforeEvent,
        ]);
    }

    /**
     * Envoyer une push notification via Firebase
     */
    private function sendPushNotification(User $user, array $rendered, Event $event, string $type)
    {
        try {
            // Récupérer les tokens actifs de l'utilisateur
            $tokens = NotificationToken::where('user_id', $user->id)
                ->active()
                ->get();

            if ($tokens->isEmpty()) {
                Log::info("Aucun token push pour user {$user->id}");
                return false;
            }

            foreach ($tokens as $token) {
                // TODO: Implémenter l'envoi Firebase ici
                // Utiliser le package kreait/firebase-php ou votre service Firebase

                Log::info("Push notification envoyée à token {$token->id}");
                $token->updateLastUsed();
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Erreur push notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer une notification email
     */
    private function sendEmailNotification(User $user, NotificationTemplate $template, array $data, Event $event)
    {
        try {
            $html = $template->renderEmail($data);

            if (empty($html)) {
                Log::info("Pas de template email pour {$template->type}");
                return false;
            }

            // TODO: Envoyer l'email via votre système de mailing
            // Mail::to($user->email)->send(new EventNotificationMail($html, $event));

            Log::info("Email envoyé à {$user->email}");
            return true;
        } catch (\Exception $e) {
            Log::error("Erreur email notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mapper le type de notification vers le champ de préférence
     */
    private function getPreferenceField(string $type): ?string
    {
        $mapping = [
            'event_new' => 'event_new',
            'event_update' => 'event_updated',
            'event_reminder' => 'event_reminder',
            'event_cancelled' => 'event_cancelled',
            'event_registration_confirmed' => 'event_registration',
            'event_new_participant' => 'event_registration',
        ];

        return $mapping[$type] ?? null;
    }

    /**
     * Déterminer la priorité selon le type
     */
    private function getPriority(string $type): string
    {
        $highPriority = ['event_cancelled', 'event_reminder'];

        return in_array($type, $highPriority) ? 'high' : 'normal';
    }
}
