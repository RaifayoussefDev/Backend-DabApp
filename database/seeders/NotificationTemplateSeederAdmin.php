<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;

class NotificationTemplateSeederAdmin extends Seeder
{
    public function run()
    {
        NotificationTemplate::updateOrCreate(
            ['type' => 'admin_broadcast'],
            [
                'name' => 'Admin Broadcast',
                'title_template' => '{{title}}',
                'title_template_ar' => '{{title_ar}}',
                'message_template' => '{{body}}',
                'message_template_ar' => '{{body_ar}}',
                'email_template' => '<h1>{{title}}</h1><p>{{body}}</p>',
                'email_template_ar' => '<h1 style="text-align:right">{{title_ar}}</h1><p style="text-align:right">{{body_ar}}</p>',
                'description' => 'Generic template for admin announcements, promos, and news',
                'variables' => ['title', 'title_ar', 'body', 'body_ar'],
                'is_active' => true,
                'icon' => 'bullhorn',
                'color' => '#FF5722'
            ]
        );
    }
}
