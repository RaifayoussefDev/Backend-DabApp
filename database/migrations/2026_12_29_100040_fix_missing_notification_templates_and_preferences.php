<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications audit (2026-10-13):
 *  - every user gets a notification_preferences row (Google/Firebase, prospect and
 *    admin-created accounts never had one, so all their notifications were dropped);
 *  - new_listing_in_city gets its own toggle, like the trainer/course city broadcasts;
 *  - templates the code sends but that were never seeded.
 */
return new class extends Migration
{
    private function templates(): array
    {
        return [
            'event_new_participant' => [
                'name' => 'Event New Participant',
                'title' => 'New participant',
                'title_ar' => 'مشارك جديد',
                'message' => '{participant_name} registered for your event "{event_title}".',
                'message_ar' => 'سجّل {participant_name} في فعاليتك "{event_title_ar}".',
                'icon' => 'person_add',
                'color' => '#2196F3',
                'variables' => ['participant_name', 'event_title', 'event_title_ar'],
            ],
            'event_registration_confirmed' => [
                'name' => 'Event Registration Confirmed',
                'title' => 'Registration confirmed',
                'title_ar' => 'تم تأكيد التسجيل',
                'message' => 'You are registered for "{event_title}" on {event_date}.',
                'message_ar' => 'تم تسجيلك في "{event_title_ar}" بتاريخ {event_date}.',
                'icon' => 'event_available',
                'color' => '#4CAF50',
                'variables' => ['event_title', 'event_title_ar', 'event_date'],
            ],
            'guide_new_bookmark' => [
                'name' => 'Guide New Bookmark',
                'title' => 'Guide saved',
                'title_ar' => 'تم حفظ دليلك',
                'message' => '{user_name} saved your guide "{guide_title}".',
                'message_ar' => 'قام {user_name} بحفظ دليلك "{guide_title}".',
                'icon' => 'bookmark',
                'color' => '#2196F3',
                'variables' => ['user_name', 'guide_title'],
            ],
            'guide_submitted' => [
                'name' => 'Guide Submitted',
                'title' => 'Guide submitted',
                'title_ar' => 'تم إرسال الدليل',
                'message' => 'Your guide "{guide_title}" has been submitted and is under review.',
                'message_ar' => 'تم إرسال دليلك "{guide_title}" وهو قيد المراجعة.',
                'icon' => 'hourglass_top',
                'color' => '#FF9800',
                'variables' => ['guide_title'],
            ],
            'poi_approved' => [
                'name' => 'POI Approved',
                'title' => 'Place approved',
                'title_ar' => 'تمت الموافقة على المكان',
                'message' => 'Your place "{poi_name}" is now live.',
                'message_ar' => 'مكانك "{poi_name}" أصبح منشوراً الآن.',
                'icon' => 'check_circle',
                'color' => '#4CAF50',
                'variables' => ['poi_name'],
            ],
            'poi_rejected' => [
                'name' => 'POI Rejected',
                'title' => 'Place rejected',
                'title_ar' => 'تم رفض المكان',
                'message' => 'Your place "{poi_name}" was rejected. Reason: {rejection_reason}',
                'message_ar' => 'تم رفض مكانك "{poi_name}". السبب: {rejection_reason}',
                'icon' => 'cancel',
                'color' => '#F44336',
                'variables' => ['poi_name', 'rejection_reason'],
            ],
            'route_approved' => [
                'name' => 'Route Approved',
                'title' => 'Route approved',
                'title_ar' => 'تمت الموافقة على المسار',
                'message' => 'Your route "{route_title}" is now live.',
                'message_ar' => 'مسارك "{route_title}" أصبح منشوراً الآن.',
                'icon' => 'check_circle',
                'color' => '#4CAF50',
                'variables' => ['route_title'],
            ],
            'route_rejected' => [
                'name' => 'Route Rejected',
                'title' => 'Route rejected',
                'title_ar' => 'تم رفض المسار',
                'message' => 'Your route "{route_title}" was rejected. Reason: {rejection_reason}',
                'message_ar' => 'تم رفض مسارك "{route_title}". السبب: {rejection_reason}',
                'icon' => 'cancel',
                'color' => '#F44336',
                'variables' => ['route_title', 'rejection_reason'],
            ],
            'soom_created_by_admin' => [
                'name' => 'Soom Created By Admin',
                'title' => 'Offer placed for you',
                'title_ar' => 'تم تقديم عرض باسمك',
                'message' => 'An offer of {amount} AED was placed on your behalf for "{listing_title}".',
                'message_ar' => 'تم تقديم عرض بقيمة {amount} درهم باسمك على "{listing_title}".',
                'icon' => 'gavel',
                'color' => '#F03D24',
                'variables' => ['listing_title', 'amount'],
            ],
        ];
    }

    public function up(): void
    {
        if (!Schema::hasColumn('notification_preferences', 'new_listing_in_city')) {
            Schema::table('notification_preferences', function (Blueprint $table) {
                $table->boolean('new_listing_in_city')->default(true);
            });
        }

        // Backfill: one row per user, column defaults (everything on) apply
        DB::statement('
            INSERT INTO notification_preferences (user_id, created_at, updated_at)
            SELECT u.id, NOW(), NOW()
            FROM users u
            LEFT JOIN notification_preferences p ON p.user_id = u.id
            WHERE p.id IS NULL
        ');

        foreach ($this->templates() as $type => $t) {
            DB::table('notification_templates')->updateOrInsert(
                ['type' => $type],
                [
                    'name' => $t['name'],
                    'title_template' => $t['title'],
                    'title_template_ar' => $t['title_ar'],
                    'message_template' => $t['message'],
                    'message_template_ar' => $t['message_ar'],
                    'icon' => $t['icon'],
                    'color' => $t['color'],
                    'is_active' => true,
                    'variables' => json_encode($t['variables']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereIn('type', array_keys($this->templates()))->delete();

        if (Schema::hasColumn('notification_preferences', 'new_listing_in_city')) {
            Schema::table('notification_preferences', function (Blueprint $table) {
                $table->dropColumn('new_listing_in_city');
            });
        }
        // Backfilled preference rows are kept — they're valid user data.
    }
};
