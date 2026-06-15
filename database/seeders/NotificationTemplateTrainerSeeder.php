<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;

class NotificationTemplateTrainerSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'type'               => 'trainer_approved',
                'name'               => 'Trainer Approved',
                'description'        => 'Sent to trainer when their profile is approved by admin',
                'title_template'     => 'Profile Approved',
                'message_template'   => 'Congratulations! Your trainer profile has been approved. You are now visible to clients.',
                'title_template_ar'  => 'تمت الموافقة على ملفك',
                'message_template_ar'=> 'تهانينا! تمت الموافقة على ملف المدرب الخاص بك. أنت الآن مرئي للعملاء.',
                'variables'          => ['trainer_id', 'trainer_name'],
                'icon'               => 'check_circle',
                'color'              => '#4CAF50',
            ],
            [
                'type'               => 'trainer_rejected',
                'name'               => 'Trainer Rejected',
                'description'        => 'Sent to trainer when their profile is rejected by admin',
                'title_template'     => 'Profile Not Approved',
                'message_template'   => 'Your trainer application was not approved. Reason: {{reason}}',
                'title_template_ar'  => 'لم تتم الموافقة على ملفك',
                'message_template_ar'=> 'لم تتم الموافقة على طلبك كمدرب. السبب: {{reason}}',
                'variables'          => ['trainer_id', 'trainer_name', 'reason'],
                'icon'               => 'cancel',
                'color'              => '#F44336',
            ],
            [
                'type'               => 'trainer_suspended',
                'name'               => 'Trainer Suspended',
                'description'        => 'Sent to trainer when their profile is suspended by admin',
                'title_template'     => 'Account Suspended',
                'message_template'   => 'Your trainer account has been suspended. Reason: {{reason}}. Please contact support for more information.',
                'title_template_ar'  => 'تم تعليق حسابك',
                'message_template_ar'=> 'تم تعليق حساب المدرب الخاص بك. السبب: {{reason}}. يرجى التواصل مع الدعم للمزيد من المعلومات.',
                'variables'          => ['trainer_id', 'trainer_name', 'reason'],
                'icon'               => 'block',
                'color'              => '#FF9800',
            ],
            [
                'type'               => 'trainer_reactivated',
                'name'               => 'Trainer Reactivated',
                'description'        => 'Sent to trainer when their suspended profile is reactivated',
                'title_template'     => 'Account Reactivated',
                'message_template'   => 'Your trainer account has been reactivated. You are now visible to clients again.',
                'title_template_ar'  => 'تم إعادة تفعيل حسابك',
                'message_template_ar'=> 'تم إعادة تفعيل حساب المدرب الخاص بك. أنت مرئي للعملاء مجددًا.',
                'variables'          => ['trainer_id', 'trainer_name'],
                'icon'               => 'check_circle',
                'color'              => '#4CAF50',
            ],
            [
                'type'               => 'trainer_review_approved',
                'name'               => 'Trainer Review Approved',
                'description'        => 'Sent to trainer when a review on their profile is approved',
                'title_template'     => 'New Review Published',
                'message_template'   => 'A client review on your profile has been approved and is now visible.',
                'title_template_ar'  => 'تم نشر تقييم جديد',
                'message_template_ar'=> 'تمت الموافقة على تقييم عميل على ملفك الشخصي وأصبح مرئيًا الآن.',
                'variables'          => ['trainer_id', 'trainer_name'],
                'icon'               => 'star',
                'color'              => '#FFC107',
            ],
            [
                'type'               => 'trainer_session_completed',
                'name'               => 'Trainer Session Completed — Review Request',
                'description'        => 'Sent to client when their training session is marked as completed',
                'title_template'     => 'Session Completed — Leave a Review',
                'message_template'   => 'Your training session with {{trainer_name}} on {{session_date}} has been completed. Share your experience!',
                'title_template_ar'  => 'انتهت الجلسة — اترك تقييمك',
                'message_template_ar'=> 'انتهت جلسة التدريب مع {{trainer_name}} بتاريخ {{session_date}}. شارك تجربتك!',
                'variables'          => ['booking_id', 'trainer_id', 'trainer_name', 'session_date'],
                'icon'               => 'star_rate',
                'color'              => '#FFC107',
            ],
            [
                'type'               => 'trainer_booking_cancelled_by_admin',
                'name'               => 'Trainer Booking Cancelled by Admin',
                'description'        => 'Sent to trainer and client when a booking is force-cancelled by admin',
                'title_template'     => 'Booking Cancelled',
                'message_template'   => 'Your booking for {{session_date}} has been cancelled by admin. Reason: {{reason}}',
                'title_template_ar'  => 'تم إلغاء الحجز',
                'message_template_ar'=> 'تم إلغاء حجزك بتاريخ {{session_date}} من قبل الإدارة. السبب: {{reason}}',
                'variables'          => ['booking_id', 'session_date', 'reason'],
                'icon'               => 'event_busy',
                'color'              => '#F44336',
            ],
            [
                'type'               => 'trainer_booking_confirmed_by_admin',
                'name'               => 'Trainer Booking Confirmed by Admin',
                'description'        => 'Sent to trainer and client when a booking is manually confirmed by admin',
                'title_template'     => 'Booking Confirmed',
                'message_template'   => 'Your booking for {{session_date}} has been confirmed.',
                'title_template_ar'  => 'تم تأكيد الحجز',
                'message_template_ar'=> 'تم تأكيد حجزك بتاريخ {{session_date}}.',
                'variables'          => ['booking_id', 'session_date'],
                'icon'               => 'event_available',
                'color'              => '#4CAF50',
            ],
            [
                'type'               => 'trainer_payout_approved',
                'name'               => 'Trainer Payout Approved',
                'description'        => 'Sent to trainer when their payout request is approved',
                'title_template'     => 'Payout Approved',
                'message_template'   => 'Your payout request of {{amount}} {{currency}} has been approved and will be transferred soon.',
                'title_template_ar'  => 'تمت الموافقة على طلب السحب',
                'message_template_ar'=> 'تمت الموافقة على طلب سحب مبلغ {{amount}} {{currency}} وسيتم تحويله قريبًا.',
                'variables'          => ['payout_id', 'amount', 'currency'],
                'icon'               => 'account_balance_wallet',
                'color'              => '#4CAF50',
            ],
            [
                'type'               => 'trainer_payout_rejected',
                'name'               => 'Trainer Payout Rejected',
                'description'        => 'Sent to trainer when their payout request is rejected',
                'title_template'     => 'Payout Request Rejected',
                'message_template'   => 'Your payout request of {{amount}} {{currency}} was rejected. Reason: {{reason}}',
                'title_template_ar'  => 'تم رفض طلب السحب',
                'message_template_ar'=> 'تم رفض طلب سحب مبلغ {{amount}} {{currency}}. السبب: {{reason}}',
                'variables'          => ['payout_id', 'amount', 'currency', 'reason'],
                'icon'               => 'money_off',
                'color'              => '#F44336',
            ],
            [
                'type'               => 'trainer_payout_paid',
                'name'               => 'Trainer Payout Paid',
                'description'        => 'Sent to trainer when their payout has been transferred',
                'title_template'     => 'Payout Transferred',
                'message_template'   => 'Your payout of {{amount}} {{currency}} has been transferred. Reference: {{transfer_ref}}',
                'title_template_ar'  => 'تم تحويل مبلغ السحب',
                'message_template_ar'=> 'تم تحويل مبلغ {{amount}} {{currency}}. المرجع: {{transfer_ref}}',
                'variables'          => ['payout_id', 'amount', 'currency', 'transfer_ref'],
                'icon'               => 'payments',
                'color'              => '#4CAF50',
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['type' => $template['type']],
                array_merge($template, ['is_active' => true])
            );
        }
    }
}
