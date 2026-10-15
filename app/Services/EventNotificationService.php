<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration des notifications d'événements
 * Utilise le système de notifications existant (tables notifications, notification_preferences, etc.)
 */
class EventNotificationService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * Envoyer une notification à un utilisateur pour un événement
     *
     * Délègue au NotificationService : préférences, template dans la langue
     * de l'utilisateur, push FCM, email et log sont gérés au même endroit.
     *
     * @param User $user Utilisateur destinataire
     * @param Event $event Événement concerné
     * @param string $type Type de notification (event_updated, event_reminder, etc.)
     * @param array $data Données variables pour le template
     */
    public function sendToUser(User $user, Event $event, string $type, array $data = [])
    {
        $templateData = array_merge([
            'event_id' => $event->id,
            'event_title' => $event->title,
            'event_title_ar' => $event->title_ar ?? $event->title,
            'event_date' => $event->event_date?->format('Y-m-d'),
            'event_location' => $event->venue_name ?? '',
            'event_location_ar' => $event->venue_name_ar ?? '',
            'action_url' => "/events/{$event->id}",
        ], $data);

        try {
            $result = $this->notifications->sendToUser($user, $type, $templateData, [
                'entity' => $event,
                'priority' => $this->getPriority($type),
            ]);
        } catch (\Exception $e) {
            Log::error("Event notification {$type} failed for user {$user->id}: " . $e->getMessage());
            return false;
        }

        return $result['success'] ?? false;
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
        return $this->sendToParticipants($event, 'event_updated', [
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
     * Déterminer la priorité selon le type
     */
    private function getPriority(string $type): string
    {
        $highPriority = ['event_cancelled', 'event_reminder'];

        return in_array($type, $highPriority) ? 'high' : 'normal';
    }
}
