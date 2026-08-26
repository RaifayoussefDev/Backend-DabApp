<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('notification_templates')->updateOrInsert(
            ['type' => 'listing_follow_up'],
            [
                'type' => 'listing_follow_up',
                'name' => 'Listing Follow-up',
                'title_template' => 'Did you sell it?',
                'title_template_ar' => 'هل تم بيع إعلانك؟',
                'message_template' => 'How is your listing "{listing_title}" going? Let us know if it sold.',
                'message_template_ar' => 'كيف حال إعلانك "{listing_title}"؟ أخبرنا إذا تم بيع المنتج.',
                'icon' => 'help_outline',
                'color' => '#F03D24',
                'is_active' => true,
                'variables' => json_encode(['listing_id', 'listing_title']),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('notification_templates')->where('type', 'listing_follow_up')->delete();
    }
};
