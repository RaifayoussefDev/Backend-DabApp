<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationTemplateSeeder extends Seeder
{
    public function run()
    {
        $templates = [
            // Events
            [
                'type' => 'event_new_participant',
                'title' => 'New Event Participant / مشارك جديد في الحدث',
                'message' => '{{participant_name}} has joined your event "{{event_name}}" / {{participant_name}} انضم إلى حدثك "{{event_name}}"',
                'icon' => 'event',
                'color' => '#4CAF50',
                'sound' => 'default',
                'is_active' => true,
            ],
            [
                'type' => 'event_new_review',
                'title' => 'New Event Review / تقييم جديد للحدث',
                'message' => '{{reviewer_name}} rated your event "{{event_name}}" ({{rating}}⭐) / {{reviewer_name}} قيم حدثك "{{event_name}}" بـ ({{rating}}⭐)',
                'icon' => 'star',
                'color' => '#FFC107',
                'sound' => 'default',
                'is_active' => true,
            ],
            [
                'type' => 'event_starting_soon',
                'title' => 'Event Starting Soon / الحدث يبدأ قريباً',
                'message' => 'Your event "{{event_name}}" starts in {{hours}} hours / حدثك "{{event_name}}" يبدأ خلال {{hours}} ساعات',
                'icon' => 'alarm',
                'color' => '#FF9800',
                'sound' => 'default',
                'is_active' => true,
            ],
            [
                'type' => 'event_created',
                'title' => 'Event Created / تم إنشاء الحدث',
                'message' => 'Your event "{{event_name}}" has been successfully created / تم إنشاء حدثك "{{event_name}}" بنجاح',
                'icon' => 'add_circle',
                'color' => '#4CAF50',
                'sound' => 'success',
                'is_active' => true,
            ],
            
            // Guides
            [
                'type' => 'guide_new_like',
                'title' => 'New Guide Like / إعجاب جديد بالدليل',
                'message' => '{{liker_name}} liked your guide "{{guide_title}}" / {{liker_name}} أعجب بدليلك "{{guide_title}}"',
                'icon' => 'favorite',
                'color' => '#E91E63',
                'sound' => 'default',
                'is_active' => true,
            ],
            [
                'type' => 'guide_new_comment',
                'title' => 'New Guide Comment / تعليق جديد على الدليل',
                'message' => '{{commenter_name}} commented on "{{guide_title}}" / {{commenter_name}} علق على "{{guide_title}}"',
                'icon' => 'comment',
                'color' => '#2196F3',
                'sound' => 'default',
                'is_active' => true,
            ],
            [
                'type' => 'guide_new_bookmark',
                'title' => 'Guide Bookmarked / تم حفظ الدليل',
                'message' => '{{user_name}} saved your guide "{{guide_title}}" / {{user_name}} حفظ دليلك "{{guide_title}}"',
                'icon' => 'bookmark',
                'color' => '#9C27B0',
                'sound' => 'default',
                'is_active' => true,
            ],
            [
                'type' => 'guide_created',
                'title' => 'Guide Created / تم إنشاء الدليل',
                'message' => 'Your guide "{{guide_title}}" has been successfully created / تم إنشاء دليلك "{{guide_title}}" بنجاح',
                'icon' => 'library_books',
                'color' => '#4CAF50',
                'sound' => 'success',
                'is_active' => true,
            ],
            
            // Listings
            [
                'type' => 'listing_created',
                'title' => 'Listing Created / تم إنشاء الإعلان',
                'message' => 'Your listing "{{listing_title}}" has been created and is under review / تم إنشاء إعلانك "{{listing_title}}" وهو قيد المراجعة',
                'icon' => 'add_business',
                'color' => '#2196F3',
                'sound' => 'default',
                'is_active' => true,
            ],
            [
                'type' => 'listing_new_submission',
                'title' => 'New Offer / عرض جديد',
                'message' => '{{buyer_name}} made an offer for "{{listing_title}}" / {{buyer_name}} قدم عرضاً لـ "{{listing_title}}"',
                'icon' => 'local_offer',
                'color' => '#4CAF50',
                'sound' => 'default',
                'is_active' => true,
            ],
            [
                'type' => 'listing_approved',
                'title' => 'Listing Approved / تم قبول الإعلان',
                'message' => 'Your listing "{{listing_title}}" is now live / إعلانك "{{listing_title}}" أصبح نشطاً الآن',
                'icon' => 'check_circle',
                'color' => '#4CAF50',
                'sound' => 'success',
                'is_active' => true,
            ],
            [
                'type' => 'listing_rejected',
                'title' => 'Listing Rejected / تم رفض الإعلان',
                'message' => 'Your listing "{{listing_title}}" was rejected. Reason: {{reason}} / تم رفض إعلانك "{{listing_title}}". السبب: {{reason}}',
                'icon' => 'cancel',
                'color' => '#F44336',
                'sound' => 'default',
                'is_active' => true,
            ],
            [
                'type' => 'auction_won',
                'title' => 'Auction Won! / مبروك! فزت بالمزاد',
                'message' => 'You won the auction for "{{listing_title}}" / لقد فزت بالمزاد لـ "{{listing_title}}"',
                'icon' => 'emoji_events',
                'color' => '#FFD700',
                'sound' => 'success',
                'is_active' => true,
            ],
            
            // Soom (Negotiations)
            [
                'type' => 'soom_new_negotiation',
                'title' => 'New Negotiation / مفاوضة جديدة',
                'message' => '{{buyer_name}} wants to negotiate for "{{listing_title}}" / {{buyer_name}} يريد التفاوض على "{{listing_title}}"',
                'icon' => 'handshake',
                'color' => '#3F51B5',
                'sound' => 'default',
                'is_active' => true,
            ],
            [
                'type' => 'soom_counter_offer',
                'title' => 'Counter Offer / عرض مقابل',
                'message' => 'New counter offer for "{{listing_title}}" / عرض مقابل جديد لـ "{{listing_title}}"',
                'icon' => 'swap_horiz',
                'color' => '#FF9800',
                'sound' => 'default',
                'is_active' => true,
            ],
            [
                'type' => 'soom_accepted',
                'title' => 'Offer Accepted! / تم قبول العرض',
                'message' => 'Your offer for "{{listing_title}}" was accepted! / تم قبول عرضك لـ "{{listing_title}}"! 🎉',
                'icon' => 'check_circle',
                'color' => '#4CAF50',
                'sound' => 'success',
                'is_active' => true,
            ],
            [
                'type' => 'soom_rejected',
                'title' => 'Offer Rejected / تم رفض العرض',
                'message' => 'Your offer for "{{listing_title}}" was rejected / تم رفض عرضك لـ "{{listing_title}}"',
                'icon' => 'cancel',
                'color' => '#F44336',
                'sound' => 'default',
                'is_active' => true,
            ],

            // Payments
            [
                'type' => 'payment_success',
                'title' => 'Payment Successful / تم الدفع بنجاح',
                'message' => 'Payment of {{amount}} {{currency}} for "{{item_title}}" was successful / تم دفع {{amount}} {{currency}} لـ "{{item_title}}" بنجاح',
                'icon' => 'verified',
                'color' => '#4CAF50',
                'sound' => 'success',
                'is_active' => true,
            ],
            [
                'type' => 'payment_failed',
                'title' => 'Payment Failed / فشل الدفع',
                'message' => 'Payment for "{{item_title}}" failed. Please try again. / فشل الدفع لـ "{{item_title}}". يرجى المحاولة مرة أخرى.',
                'icon' => 'error',
                'color' => '#F44336',
                'sound' => 'default',
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
    }
}
