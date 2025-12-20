<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationTemplateSeeder_Email extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // LISTINGS
            [
                'type' => 'listing_approved',
                'name' => 'Listing Approved',
                'description' => 'Sent when a motorcycle listing is approved',
                'title_template' => '✅ تمت الموافقة على إعلانك',
                'message_template' => 'تمت الموافقة على إعلانك "{{listing_title}}" وهو الآن متاح للجميع!',
                'email_template' => '<h3>مبروك! 🎉</h3><p>تمت الموافقة على إعلانك <strong>{{listing_title}}</strong> وأصبح الآن مرئيًا لجميع المستخدمين في DabApp.</p><p>يمكنك الآن البدء في تلقي العروض والرسائل من المشترين المهتمين.</p>',
                'icon' => 'check_circle',
                'color' => '#4CAF50',
                'sound' => 'default',
                'variables' => json_encode(['listing_title', 'listing_id']),
                'is_active' => true,
            ],
            [
                'type' => 'listing_rejected',
                'name' => 'Listing Rejected',
                'description' => 'Sent when a listing is rejected',
                'title_template' => '❌ تم رفض إعلانك',
                'message_template' => 'تم رفض إعلانك "{{listing_title}}". السبب: {{reason}}',
                'email_template' => '<h3>تم رفض إعلانك</h3><p>للأسف، تم رفض إعلانك <strong>{{listing_title}}</strong>.</p><p><strong>السبب:</strong> {{reason}}</p><p>يرجى تعديل الإعلان وفقًا للملاحظات وإعادة تقديمه.</p>',
                'icon' => 'cancel',
                'color' => '#F44336',
                'sound' => 'default',
                'variables' => json_encode(['listing_title', 'reason']),
                'is_active' => true,
            ],
            [
                'type' => 'listing_expired',
                'name' => 'Listing Expired',
                'description' => 'Sent when a listing expires',
                'title_template' => '⏰ انتهت صلاحية إعلانك',
                'message_template' => 'انتهت صلاحية إعلانك "{{listing_title}}". هل تريد تجديده؟',
                'email_template' => '<h3>انتهت صلاحية إعلانك</h3><p>إعلانك <strong>{{listing_title}}</strong> لم يعد نشطًا.</p><p>يمكنك تجديد الإعلان لمواصلة استقبال العروض من المشترين المهتمين.</p>',
                'icon' => 'schedule',
                'color' => '#FF9800',
                'sound' => 'default',
                'variables' => json_encode(['listing_title', 'listing_id']),
                'is_active' => true,
            ],
            [
                'type' => 'listing_sold',
                'name' => 'Listing Sold',
                'description' => 'Sent when listing is marked as sold',
                'title_template' => '🎉 تم بيع دراجتك!',
                'message_template' => 'تهانينا! تم وضع علامة "مباع" على {{listing_title}}',
                'email_template' => '<h3>مبروك البيع! 🎉</h3><p>تم بيع <strong>{{listing_title}}</strong> بنجاح.</p><p>نتمنى لك صفقات ناجحة أخرى على DabApp!</p>',
                'icon' => 'celebration',
                'color' => '#4CAF50',
                'sound' => 'success',
                'variables' => json_encode(['listing_title', 'listing_id']),
                'is_active' => true,
            ],

            // AUCTIONS
            [
                'type' => 'bid_placed',
                'name' => 'New Bid Placed',
                'description' => 'Sent when someone places a bid on your listing',
                'title_template' => '🔨 عرض جديد على {{listing_title}}',
                'message_template' => 'قدم {{bidder_name}} عرضًا بقيمة {{bid_amount}} على {{listing_title}}',
                'email_template' => '<h3>عرض جديد! 🔨</h3><p>تلقيت عرضًا جديدًا على <strong>{{listing_title}}</strong></p><p><strong>المشتري:</strong> {{bidder_name}}<br><strong>قيمة العرض:</strong> {{bid_amount}}</p><p>يمكنك قبول أو رفض العرض من لوحة التحكم.</p>',
                'icon' => 'gavel',
                'color' => '#2196F3',
                'sound' => 'default',
                'variables' => json_encode(['listing_title', 'bid_amount', 'bidder_name']),
                'is_active' => true,
            ],
            [
                'type' => 'bid_accepted',
                'name' => 'Bid Accepted',
                'description' => 'Sent when your bid is accepted',
                'title_template' => '🎉 تم قبول عرضك!',
                'message_template' => 'مبروك! تم قبول عرضك بقيمة {{bid_amount}} على {{listing_title}}',
                'email_template' => '<h3>تهانينا! 🎉</h3><p>تم قبول عرضك على <strong>{{listing_title}}</strong></p><p><strong>قيمة العرض:</strong> {{bid_amount}}</p><p>سيتم التواصل معك قريبًا لإتمام عملية الشراء.</p>',
                'icon' => 'celebration',
                'color' => '#4CAF50',
                'sound' => 'success',
                'variables' => json_encode(['listing_title', 'bid_amount']),
                'is_active' => true,
            ],
            [
                'type' => 'bid_rejected',
                'name' => 'Bid Rejected',
                'description' => 'Sent when your bid is rejected',
                'title_template' => '❌ تم رفض عرضك',
                'message_template' => 'تم رفض عرضك بقيمة {{bid_amount}} على {{listing_title}}',
                'email_template' => '<h3>تم رفض عرضك</h3><p>للأسف، تم رفض عرضك على <strong>{{listing_title}}</strong></p><p><strong>قيمة العرض المرفوض:</strong> {{bid_amount}}</p><p>يمكنك تقديم عرض جديد أو البحث عن دراجات أخرى.</p>',
                'icon' => 'cancel',
                'color' => '#F44336',
                'sound' => 'default',
                'variables' => json_encode(['listing_title', 'bid_amount']),
                'is_active' => true,
            ],
            [
                'type' => 'bid_outbid',
                'name' => 'Outbid',
                'description' => 'Sent when someone outbids you',
                'title_template' => '⚠️ تم التفوق على عرضك',
                'message_template' => 'قدم شخص عرضًا بقيمة {{new_bid_amount}} على {{listing_title}}. عرضك كان {{your_bid_amount}}',
                'email_template' => '<h3>تم التفوق على عرضك</h3><p>تم تقديم عرض أعلى على <strong>{{listing_title}}</strong></p><p><strong>عرضك:</strong> {{your_bid_amount}}<br><strong>العرض الجديد:</strong> {{new_bid_amount}}</p><p>قدم عرضًا أعلى إذا كنت لا تزال مهتمًا!</p>',
                'icon' => 'trending_up',
                'color' => '#FF5722',
                'sound' => 'default',
                'variables' => json_encode(['listing_title', 'new_bid_amount', 'your_bid_amount']),
                'is_active' => true,
            ],

            // PAYMENTS
            [
                'type' => 'payment_success',
                'name' => 'Payment Successful',
                'description' => 'Sent when payment is successful',
                'title_template' => '✅ تمت عملية الدفع بنجاح',
                'message_template' => 'تمت عملية الدفع بقيمة {{amount}} بنجاح. رقم المعاملة: {{transaction_id}}',
                'email_template' => '<h3>الدفع الناجح ✅</h3><p>تمت عملية الدفع بنجاح!</p><p><strong>المبلغ:</strong> {{amount}}<br><strong>رقم المعاملة:</strong> {{transaction_id}}<br><strong>التاريخ:</strong> {{payment_date}}</p><p>شكرًا لاستخدامك DabApp!</p>',
                'icon' => 'payment',
                'color' => '#4CAF50',
                'sound' => 'success',
                'variables' => json_encode(['amount', 'transaction_id', 'payment_date']),
                'is_active' => true,
            ],
            [
                'type' => 'payment_failed',
                'name' => 'Payment Failed',
                'description' => 'Sent when payment fails',
                'title_template' => '❌ فشلت عملية الدفع',
                'message_template' => 'فشلت عملية الدفع بقيمة {{amount}}. يرجى المحاولة مرة أخرى.',
                'email_template' => '<h3>فشل الدفع</h3><p>للأسف، فشلت عملية الدفع.</p><p><strong>المبلغ:</strong> {{amount}}<br><strong>السبب:</strong> {{reason}}</p><p>يرجى التحقق من معلومات الدفع والمحاولة مرة أخرى.</p>',
                'icon' => 'error',
                'color' => '#F44336',
                'sound' => 'error',
                'variables' => json_encode(['amount', 'reason']),
                'is_active' => true,
            ],

            // MESSAGES
            [
                'type' => 'new_message',
                'name' => 'New Message',
                'description' => 'Sent when you receive a new message',
                'title_template' => '💬 رسالة جديدة من {{sender_name}}',
                'message_template' => '{{message_preview}}',
                'email_template' => '<h3>رسالة جديدة 💬</h3><p>تلقيت رسالة جديدة من <strong>{{sender_name}}</strong></p><p><em>"{{message_preview}}"</em></p><p>افتح التطبيق للرد على الرسالة.</p>',
                'icon' => 'message',
                'color' => '#2196F3',
                'sound' => 'message',
                'variables' => json_encode(['sender_name', 'message_preview']),
                'is_active' => true,
            ],

            // SYSTEM
            [
                'type' => 'admin_custom',
                'name' => 'Admin Custom',
                'description' => 'Custom notifications from admin',
                'title_template' => '{{custom_title}}',
                'message_template' => '{{custom_message}}',
                'email_template' => '<p>{{custom_message}}</p>',
                'icon' => 'announcement',
                'color' => '#f03d24',
                'sound' => 'default',
                'variables' => json_encode(['custom_title', 'custom_message']),
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            DB::table('notification_templates')->updateOrInsert(
                ['type' => $template['type']],
                array_merge($template, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✅ ' . count($templates) . ' notification templates seeded successfully!');
    }
}
