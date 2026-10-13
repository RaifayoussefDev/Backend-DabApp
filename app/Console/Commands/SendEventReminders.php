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
    protected $description = 'Send event reminders: 24h before (event_reminder) and ~1h before (event_starting_soon)';

    /**
     * Execute the console command. Runs hourly; each window is one hour wide,
     * so every event falls in exactly one run per reminder.
     */
    public function handle(EventNotificationService $notificationService)
    {
        $this->info('Starting event reminders check...');

        $now = Carbon::now();
        $this->remind($notificationService, 'event_reminder', 24, $now->copy()->addHours(24)->subMinutes(30), $now->copy()->addHours(24)->addMinutes(30));
        $this->remind($notificationService, 'event_starting_soon', 1, $now->copy()->addMinutes(30), $now->copy()->addMinutes(90));

        $this->info('Event reminders check completed.');
    }

    private function remind(EventNotificationService $notificationService, string $type, int $hours, Carbon $from, Carbon $to): void
    {
        // Live events are 'published' today; 'upcoming' is the legacy default status
        $events = Event::whereIn('status', ['published', 'upcoming'])
            ->whereBetween('event_date', [$from->toDateString(), $to->toDateString()]) // Simple date check first
            ->get();

        foreach ($events as $event) {
            // start_time null = all-day event (midnight)
            $eventDateTime = Carbon::parse($event->event_date->format('Y-m-d') . ' ' . $event->start_time);

            if (!$eventDateTime->between($from, $to)) {
                continue;
            }

            $this->info("Sending {$type} for event: {$event->title}");

            try {
                // Participants + interested users, each person once
                $users = $event->participants()->with('user')->get()->pluck('user')
                    ->merge($event->interestedUsers)
                    ->filter()
                    ->unique('id');

                foreach ($users as $user) {
                    $notificationService->sendToUser($user, $event, $type, ['hours' => $hours]);
                }
            } catch (\Exception $e) {
                Log::error("Failed to send {$type} for event {$event->id}: " . $e->getMessage());
                $this->error("Error sending {$type} for {$event->title}");
            }
        }
    }
}
