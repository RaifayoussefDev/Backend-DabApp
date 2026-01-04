<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'type' => 'new_report',
                'name' => 'New Report Notification',
                'description' => 'Notification sent to admins when a new report is created',
                'title_template' => 'New Report Submitted: {{reason}}',
                'message_template' => 'A new report has been submitted for {{item}}. ID: {{report_id}}.',
                'title_template_ar' => 'تم تقديم بلاغ جديد: {{reason}}',
                'message_template_ar' => 'تم تقديم بلاغ جديد بخصوص {{item}}. المعرف: {{report_id}}.',
                'variables' => ['reason', 'item', 'report_id'],
            ],
            [
                'type' => 'report_received',
                'name' => 'Report Received Confirmation',
                'description' => 'Notification sent to user knowing their report is received',
                'title_template' => 'Report Received',
                'message_template' => 'We have received your report regarding {{item}}. We will review it shortly.',
                'title_template_ar' => 'تم استلام بلاغك',
                'message_template_ar' => 'لقد استلمنا بلاغك بخصوص {{item}}. سنقوم بمراجعته قريباً.',
                'variables' => ['item', 'report_id'],
            ],
            [
                'type' => 'report_status_updated',
                'name' => 'Report Status Updated',
                'description' => 'Notification sent to user when report status changes',
                'title_template' => 'Report Status Updated',
                'message_template' => 'Your report regarding {{item}} has been marked as {{status}}.',
                'title_template_ar' => 'تحديث حالة البلاغ',
                'message_template_ar' => 'تم تحديث حالة بلاغك بخصوص {{item}} إلى {{status}}.',
                'variables' => ['item', 'status', 'report_id'],
            ]
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['type' => $template['type']],
                [
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'title_template' => $template['title_template'],
                    'message_template' => $template['message_template'],
                    'title_template_ar' => $template['title_template_ar'],
                    'message_template_ar' => $template['message_template_ar'],
                    'variables' => $template['variables'],
                    'is_active' => true,
                ]
            );
        }
    }
}
