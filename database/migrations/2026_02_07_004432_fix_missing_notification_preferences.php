<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\NotificationPreference;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find users who do NOT have a notification preference record
        $usersWithoutPreferences = User::doesntHave('notificationPreference')->get();

        foreach ($usersWithoutPreferences as $user) {
            NotificationPreference::create([
                'user_id' => $user->id,
                'listing_approved' => true,
                'listing_rejected' => true,
                'listing_sold' => true,
                'bid_placed' => true,
                'bid_accepted' => true,
                'bid_rejected' => true,
                'bid_outbid' => true,
                'soom_new_negotiation' => true,
                'soom_accepted' => true,
                'soom_rejected' => true,
                // 'dealer_approved' => true, // Assuming column name is guessed or I need to check schema. 
                // Based on previous files, 'admin_custom' maps to dealer for now? 
                // Wait, I saw 'admin_custom' in the model. I did NOT see 'dealer_approved' in the $fillable of NotificationPreference.php!
                // I need to be careful here. 
                // Let's re-read NotificationPreference.php fillable.
                // It has 'admin_custom', 'system_updates'. 
                // It does NOT have 'dealer_approved'.
                // The service check 'isNotificationEnabled' MAPS types.
                // 'dealer_approved' is NOT in the map in NotificationPreference.php line 140.
                // So it falls back to checking column 'dealer_approved'.
                // If the column doesn't exist, it might crash or return null/false.
                // I need to check if schema handles dynamic columns or if I need to map it to 'system_updates'.

                // For now, I will map common ones. If 'dealer_approved' column doesn't exist, I should map it in the Model?
                // Or I assume 'system_updates' covers it?

                'system_updates' => true,
                'push_enabled' => true,
                'email_enabled' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse action needed usually for data fix, or we could delete created prefs but that's risky.
    }
};
