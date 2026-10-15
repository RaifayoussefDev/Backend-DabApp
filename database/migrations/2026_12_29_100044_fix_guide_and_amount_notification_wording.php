<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wording found while testing on a real phone (2026-10-15):
 *  - prod's new_guide_published said "Guide Created … successfully created" to every user;
 *  - guide_published (sent to the author) read like the public broadcast;
 *  - amounts had no currency ("with 1500").
 * Set explicitly, so prod and local stop drifting.
 */
return new class extends Migration
{
    private function templates(): array
    {
        return [
            'guide_published' => [
                'title' => 'Guide published',
                'title_ar' => 'تم نشر دليلك',
                'message' => 'Your guide "{{guide_title}}" has been published and is now visible to everyone.',
                'message_ar' => 'تم نشر دليلك "{{guide_title}}" وأصبح متاحاً للجميع.',
            ],
            'new_guide_published' => [
                'title' => 'New guide',
                'title_ar' => 'دليل جديد',
                'message' => 'New guide "{{guide_title}}" is now available. Check it out!',
                'message_ar' => 'دليل جديد "{{guide_title}}" متاح الآن. اطلع عليه!',
            ],
            'soom_new_negotiation' => [
                'title' => 'New Offer Received',
                'title_ar' => 'عرض جديد',
                'message' => 'You have received a new offer of {{amount}} AED for "{{listing_title}}".',
                'message_ar' => 'لقد تلقيت عرضاً جديداً بقيمة {{amount}} درهم على "{{listing_title}}".',
            ],
            'soom_outbid' => [
                'title' => 'You Were Outbid',
                'title_ar' => 'تم تجاوز عرضك',
                'message' => 'Someone offered {{new_bid_amount}} AED on "{{soom_title}}". Place a higher offer!',
                'message_ar' => 'قدّم شخص عرضاً بقيمة {{new_bid_amount}} درهم على "{{soom_title}}". قدّم عرضاً أعلى!',
            ],
            'soom_won' => [
                'title' => 'Offer won!',
                'title_ar' => 'مبروك! فزت بالعرض',
                'message' => 'You won "{{soom_title}}" with {{winning_bid}} AED.',
                'message_ar' => 'فزت بـ "{{soom_title}}" بعرض {{winning_bid}} درهم.',
            ],
            'payment_failed' => [
                'title' => 'Payment Failed',
                'title_ar' => 'فشل الدفع',
                'message' => 'Your payment of {{amount}} AED failed. Please try again.',
                'message_ar' => 'فشل دفع {{amount}} درهم. يرجى المحاولة مرة أخرى.',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->templates() as $type => $t) {
            DB::table('notification_templates')->where('type', $type)->update([
                'title_template' => $t['title'],
                'title_template_ar' => $t['title_ar'],
                'message_template' => $t['message'],
                'message_template_ar' => $t['message_ar'],
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Wording-only fix: previous texts differed between environments, nothing to restore.
    }
};
