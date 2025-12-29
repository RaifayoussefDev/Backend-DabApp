<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Services\EventNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendEventReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:send-reminders';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Send reminders for events starting in the next 24 hours';

    /**
     * Execute the console command.
     */
    public function handle(EventNotificationService $notificationService)
    {
        $this->info('Starting event reminders check...');

        // Find events starting between 23.5 and 24.5 hours from now
        // This allows the command to run hourly without missing or duplicating events too easily
        $startWindow = Carbon::now()->addHours(24)->subMinutes(30);
        $endWindow = Carbon::now()->addHours(24)->addMinutes(30);

        $events = Event::where('status', 'upcoming')
            ->whereBetween('event_date', [$startWindow->toDateString(), $endWindow->toDateString()]) // Simple date check first
            // Advanced time check if needed, but 'event_date' is often just a Date. 
            // If start_time exists, we could combine them.
            // For now, let's assume we remind based on date or loosely on time.
            ->get();

        $this->info("Found " . $events->count() . " events potentially needing reminders.");

        foreach ($events as $event) {
            // Check if we actually want to send it now (e.g. if start_time is set)
            // If start_time is null, we assume all day, so sending 24h before date is fine.
            // If start_time is set, we check if it falls in the window.
           
            $eventDateTime = Carbon::parse($event->event_date->format('Y-m-d') . ' ' . $event->start_time);
            
            // Check if event is approx 24h away (+/- 30 mins)
            if ($eventDateTime->between($startWindow, $endWindow)) {
                 $this->info("Sending reminder for event: {$event->title}");
                 
                 try {
                     // Notify interested users
                     $notificationService->sendToInterestedUsers($event, 'event_reminder', [
                         'hours' => 24
                     ]);

                     // Notify participants
                     $notificationService->sendToParticipants($event, 'event_reminder', [
                         'hours' => 24
                     ]);
                     
                 } catch (\Exception $e) {
                     Log::error("Failed to send reminder for event {$event->id}: " . $e->getMessage());
                     $this->error("Error sending reminder for {$event->title}");
                 }
            } else {
                 $this->info("Skipping event: {$event->title} (Starts at {$eventDateTime}, not within ~24h window)");
            }
        }

        $this->info('Event reminders check completed.');
    }
}
