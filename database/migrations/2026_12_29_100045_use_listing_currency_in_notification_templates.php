<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amounts were shown with a hard-coded currency (AED / درهم), but a listing is priced
 * in its country's currency (SAR, AED, KWD…). NotificationService now passes
 * {{currency}} / {{currency_ar}} from the listing (or the payment for payment_failed).
 */
return new class extends Migration
{
    private function templates(): array
    {
        return [
            'soom_new_negotiation' => [
                'message' => 'You have received a new offer of {{amount}} {{currency}} for "{{listing_title}}".',
                'message_ar' => 'لقد تلقيت عرضاً جديداً بقيمة {{amount}} {{currency_ar}} على "{{listing_title}}".',
            ],
            'soom_outbid' => [
                'message' => 'Someone offered {{new_bid_amount}} {{currency}} on "{{soom_title}}". Place a higher offer!',
                'message_ar' => 'قدّم شخص عرضاً بقيمة {{new_bid_amount}} {{currency_ar}} على "{{soom_title}}". قدّم عرضاً أعلى!',
            ],
            'soom_won' => [
                'message' => 'You won "{{soom_title}}" with {{winning_bid}} {{currency}}.',
                'message_ar' => 'فزت بـ "{{soom_title}}" بعرض {{winning_bid}} {{currency_ar}}.',
            ],
            'soom_created_by_admin' => [
                'message' => 'An offer of {{amount}} {{currency}} was placed on your behalf for "{{listing_title}}".',
                'message_ar' => 'تم تقديم عرض بقيمة {{amount}} {{currency_ar}} باسمك على "{{listing_title}}".',
            ],
            'payment_failed' => [
                'message' => 'Your payment of {{amount}} {{currency}} failed. Please try again.',
                'message_ar' => 'فشل دفع {{amount}} {{currency_ar}}. يرجى المحاولة مرة أخرى.',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->templates() as $type => $t) {
            DB::table('notification_templates')->where('type', $type)->update([
                'message_template' => $t['message'],
                'message_template_ar' => $t['message_ar'],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Wording-only change.
    }
};
