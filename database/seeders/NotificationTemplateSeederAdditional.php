<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;

class NotificationTemplateSeederAdditional extends Seeder
{
    public function run()
    {
        $templates = [
            [
                'type' => 'new_listing_in_city',
                'name' => 'New Listing in City',
                'title_template' => 'New Listing Nearby',
                'title_template_ar' => 'إعلان جديد بالقرب منك',
                'message_template' => 'A new listing "{{listing_title}}" has been posted in {{city_name}}. Check it out!',
                'message_template_ar' => 'تم نشر إعلان جديد "{{listing_title}}" في {{city_name_ar}}. تحقق منه!',
                'description' => 'Notification sent to users in the same city as a new listing',
                'variables' => ['listing_title', 'city_name', 'city_name_ar', 'listing_id', 'price'],
                'is_active' => true,
            ],
            [
                'type' => 'listing_updated',
                'name' => 'Listing Updated',
                'title_template' => 'Listing Updated',
                'title_template_ar' => 'تم تحديث الإعلان',
                'message_template' => 'The listing "{{listing_title}}" has been updated.',
                'message_template_ar' => 'تم تحديث الإعلان "{{listing_title}}".',
                'description' => 'Notification sent when a listing is updated',
                'variables' => ['listing_title', 'listing_id', 'updated_at'],
                'is_active' => true,
            ],
            [
                'type' => 'guide_updated',
                'name' => 'Guide Updated',
                'title_template' => 'Guide Updated',
                'title_template_ar' => 'تم تحديث الدليل',
                'message_template' => 'The guide "{{guide_title}}" has been updated. See what\'s new!',
                'message_template_ar' => 'تم تحديث الدليل "{{guide_title}}". شاهد الجديد!',
                'description' => 'Notification sent when a guide is updated',
                'variables' => ['guide_title', 'guide_id'],
                'is_active' => true,
            ],
            [
                'type' => 'event_reminder',
                'name' => 'Event Reminder',
                'title_template' => 'Event Reminder',
                'title_template_ar' => 'تذكير بالحدث',
                'message_template' => 'Reminder: "{{event_title}}" starts in {{hours}} hours!',
                'message_template_ar' => 'تذكير: "{{event_title_ar}}" يبدأ خلال {{hours}} ساعات!',
                'description' => 'Notification sent 24h before event starts',
                'variables' => ['event_title', 'event_title_ar', 'hours'],
                'is_active' => true,
            ],
            [
                'type' => 'guide_new_comment',
                'name' => 'New Comment on Guide',
                'title_template' => 'New Comment on Guide',
                'title_template_ar' => 'تعليق جديد على الدليل',
                'message_template' => '{{user_name}} commented on your guide "{{guide_title}}".',
                'message_template_ar' => 'علق {{user_name}} على دليلك "{{guide_title}}".',
                'description' => 'Notification sent to author when a new comment is added',
                'variables' => ['user_name', 'guide_title'],
                'is_active' => true,
            ]
        ];

        foreach ($templates as $data) {
            NotificationTemplate::updateOrCreate(
                ['type' => $data['type']],
                $data
            );
        }
    }
}
