<?php

   namespace App\Observers;

   use App\Models\Event;
   use App\Services\EventNotificationService;

   class EventObserver
   {
       protected $notificationService;

       public function __construct(EventNotificationService $notificationService)
       {
           $this->notificationService = $notificationService;
       }

       /**
        * Handle the Event "updated" event.
        */
       public function updated(Event $event)
       {
           // Si l'événement est annulé
           if ($event->isDirty('status') && $event->status === 'cancelled') {
               $this->notificationService->notifyEventCancelled($event);
           }

           // Si des détails importants ont changé
           $importantFields = ['event_date', 'start_time', 'end_time', 'venue_name', 'address'];
           foreach ($importantFields as $field) {
               if ($event->isDirty($field)) {
                   $this->notificationService->notifyParticipantsEventUpdated(
                       $event,
                       'Please check the updated event details.'
                   );
                   break;
               }
           }
       }
   }
