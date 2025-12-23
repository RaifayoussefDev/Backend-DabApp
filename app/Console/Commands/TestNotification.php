<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\NotificationService;

class TestNotification extends Command
{
    protected $signature = 'test:notification {email=test@dabapp.com}';
    protected $description = 'Send test notification to user';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("❌ User not found with email: {$email}");
            $this->info('💡 Create a user first with: php artisan test:create-user');
            return 1;
        }

        $this->info("📤 Sending test notification to: {$user->first_name} {$user->last_name} ({$user->email})");

        try {
            $service = app(NotificationService::class);

            $result = $service->sendToUser(
                $user,
                'listing_approved',
                [
                    'listing_title' => 'Honda CBR 600RR 2020',
                    'listing_id' => 123,
                ]
            );

            $this->newLine();

            if ($result['success']) {
                $this->info('✅ Notification sent successfully!');
                $this->info('Notification ID: ' . $result['notification_id']);

                if (isset($result['push_results']) && is_array($result['push_results'])) {
                    $this->newLine();
                    $this->info('📱 Push Notification Results:');

                    $total = $result['push_results']['total'] ?? 0;
                    $sent = $result['push_results']['sent'] ?? 0;
                    $failed = $result['push_results']['failed'] ?? 0;

                    $this->info("Total tokens: {$total}");
                    $this->info("Sent: {$sent}");
                    $this->info("Failed: {$failed}");

                    if ($total == 0) {
                        $this->newLine();
                        $this->warn('⚠️  No FCM tokens found for this user.');
                        $this->info('💡 Register a device token via API: POST /api/notification-tokens');
                    }
                }
            } else {
                $this->error('❌ Failed to send notification');
                $this->error('Reason: ' . ($result['message'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
