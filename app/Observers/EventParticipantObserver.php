<?php

   namespace App\Observers;

   use App\Models\EventParticipant;
   use App\Services\EventNotificationService;

   class EventParticipantObserver
   {
       protected $notificationService;

       public function __construct(EventNotificationService $notificationService)
       {
           $this->notificationService = $notificationService;
       }

       /**
        * Handle the EventParticipant "created" event.
        */
       public function created(EventParticipant $participant)
       {
           // Notifier l'organisateur du nouveau participant
           $this->notificationService->notifyOrganizerNewParticipant(
               $participant->event,
               $participant->user
           );

           // Notifier le participant de la confirmation
           $this->notificationService->notifyParticipantRegistrationConfirmed(
               $participant->event,
               $participant->user
           );

           // Incrémenter le compteur de participants
           $participant->event->increment('participants_count');
       }

       /**
        * Handle the EventParticipant "deleted" event.
        */
       public function deleted(EventParticipant $participant)
       {
           // Décrémenter le compteur de participants
           $participant->event->decrement('participants_count');
       }
   }
