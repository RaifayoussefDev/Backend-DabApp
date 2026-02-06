<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;

class TestDealerFlow extends Command
{
    protected $signature = 'test:dealer-flow';
    protected $description = 'Verify Dealer Feature Flow (Admin Update, Notification, Listing)';

    public function handle()
    {
        $this->info('Starting Dealer Flow Verification...');

        // 1. Create Test User
        $user = User::factory()->create([
            'is_dealer' => false,
            'email' => 'test_dealer_' . time() . '@example.com'
        ]);
        $this->info("Created Test User: {$user->id} ({$user->email})");

        // Create Preferences for Test User
        \App\Models\NotificationPreference::create([
            'user_id' => $user->id,
            'dealer_approved' => true,
            'dealer_removed' => true,
            'system_updates' => true,
            'push_enabled' => true,
            'email_enabled' => true,
        ]);

        // 2. Simulate Admin Update (using Controller logic manually or calling method)
        // We act as Admin
        $controller = new UserController();
        $request = Request::create("/api/admin/users/{$user->id}", 'PUT', [
            'is_dealer' => true,
            'dealer_title' => 'Test Dealer Corp',
            'dealer_address' => '123 Test St',
            'dealer_phone' => '999999999',
            'latitude' => 25.0,
            'longitude' => 55.0
        ]);

        // Mock Auth for Admin check if needed (UserController might use Auth::id() for notification data)
        \Illuminate\Support\Facades\Auth::loginUsingId(1); // Assuming ID 1 is admin

        $response = $controller->update($request, $user->id);

        $user->refresh();

        if ($user->is_dealer && $user->dealer_title === 'Test Dealer Corp') {
            $this->info('✅ Admin Update Success: User is now a dealer.');
        } else {
            $this->error('❌ Admin Update Failed: User is NOT a dealer.');
            $this->error("is_dealer: " . ($user->is_dealer ? 'true' : 'false'));
            $this->error("dealer_title: " . $user->dealer_title);
        }

        // 3. Verify Notification
        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'dealer_approved')
            ->latest()
            ->first();

        if ($notification) {
            $this->info('✅ Notification Success: Dealer Approved notification found.');
        } else {
            $this->error('❌ Notification Failed: No Dealer Approved notification found.');
        }

        // 4. Simulate REMOVE Dealer
        $this->info('Testing Dealer Removal...');
        $removeRequest = Request::create("/api/admin/users/{$user->id}", 'PUT', [
            'is_dealer' => false,
        ]);
        $controller->update($removeRequest, $user->id);
        $user->refresh();

        if (!$user->is_dealer) {
            $this->info('✅ Admin Remove Success: User is no longer a dealer.');
        } else {
            $this->error('❌ Admin Remove Failed: User is still a dealer.');
        }

        // 5. Verify Removal Notification
        $removeNotification = Notification::where('user_id', $user->id)
            ->where('type', 'dealer_removed')
            ->latest()
            ->first();

        if ($removeNotification) {
            $this->info('✅ Notification Success: Dealer Removed notification found.');
        } else {
            $this->error('❌ Notification Failed: No Dealer Removed notification found.');
        }

        // Cleanup
        $user->delete();
        if ($notification)
            $notification->delete();

        $this->info('Test Complete.');
    }
}
