<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Arabic notifications were showing English values (review of every template, 2026-10-17):
 *  - trainer payouts: "2200 SAR" instead of "2200 ريال"   → {{currency_ar}}
 *  - new trainer / course in city: "في Jeddah"           → {{city_name_ar}} (already sent in the data)
 *  - reports: "بخصوص listing … إلى resolved"              → {{item_ar}} / {{status_ar}} (NotificationService)
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['trainer_payout_approved', 'trainer_payout_rejected', 'trainer_payout_paid'] as $type) {
            DB::table('notification_templates')->where('type', $type)->update([
                'message_template_ar' => DB::raw("REPLACE(message_template_ar, '{{currency}}', '{{currency_ar}}')"),
                'updated_at' => now(),
            ]);
        }

        foreach (['new_trainer_in_city', 'new_course_in_city'] as $type) {
            DB::table('notification_templates')->where('type', $type)->update([
                'title_template_ar' => DB::raw("REPLACE(title_template_ar, '{{city_name}}', '{{city_name_ar}}')"),
                'message_template_ar' => DB::raw("REPLACE(message_template_ar, '{{city_name}}', '{{city_name_ar}}')"),
                'updated_at' => now(),
            ]);
        }

        DB::table('notification_templates')->where('type', 'report_received')->update([
            'message_template' => 'We have received {{item_en}}. We will review it shortly.',
            'message_template_ar' => 'لقد استلمنا بلاغك بخصوص {{item_ar}}. سنقوم بمراجعته قريباً.',
            'updated_at' => now(),
        ]);
        DB::table('notification_templates')->where('type', 'report_status_updated')->update([
            'message_template' => 'The status of {{item_en}} is now: {{status_en}}.',
            'message_template_ar' => 'تم تحديث حالة بلاغك بخصوص {{item_ar}} إلى: {{status_ar}}.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Wording-only change.
    }
};
