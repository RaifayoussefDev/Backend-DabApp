<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateUserOnlineStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:update-online-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update users online status based on token expiration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();

        \Log::info('UpdateUserOnlineStatus command started');

        // 1. Mark expired sessions in Authentication table as offline
        // (Assuming refresh_token_expiration is the ultimate session expiration)
        $expiredSessions = \App\Models\Authentication::where('is_online', true)
            ->where('refresh_token_expiration', '<', $now)
            ->update(['is_online' => false]);

        if ($expiredSessions > 0) {
            \Log::info("Marked {$expiredSessions} expired authentication sessions as offline.");
        }

        // 2. Find online users who NO LONGER have any active (unexpired) authentication sessions
        // using whereDoesntHave
        $offlineUsersCount = \App\Models\User::where('is_online', true)
            ->whereDoesntHave('authentications', function ($query) use ($now) {
                // A session is considered active if either the access or refresh token is still valid
                $query->where('refresh_token_expiration', '>=', $now)
                    ->orWhere('token_expiration', '>=', $now);
            })
            ->update(['is_online' => false]);

        if ($offlineUsersCount > 0) {
            \Log::info("Marked {$offlineUsersCount} users as offline due to lack of active sessions.");
        }

        \Log::info('UpdateUserOnlineStatus command finished');
    }
}
