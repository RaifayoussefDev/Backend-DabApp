<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;

class EnsureNotificationTemplatesSeeder extends Seeder
{
    public function run()
    {
        $templates = [
            [
                'type' => 'dealer_approved',
                'title_en' => 'Dealer Status Approved',
                'title_ar' => 'تمت الموافقة sur le statut de revendeur', // (corrected later)
                'message_en' => 'Congratulations! Your account has been upgraded to Dealer status.',
                'message_ar' => 'تهانينا! تمت ترقية حسابك إلى وضع التاجر.',
            ],
            [
                'type' => 'soom_new_negotiation',
                'title_en' => 'New Offer Received',
                'title_ar' => 'عرض جديد تم استلامه',
                'message_en' => 'You have received a new offer of {amount} for {listing_title}.',
                'message_ar' => 'لقد تلقيت عرضًا جديدًا بقيمة {amount} لـ {listing_title}.',
            ],
            [
                'type' => 'soom_accepted',
                'title_en' => 'Offer Accepted',
                'title_ar' => 'تم قبول العرض',
                'message_en' => 'Your offer for {listing_title} has been accepted!',
                'message_ar' => 'تم قبول عرضك لـ {listing_title}!',
            ],
            [
                'type' => 'soom_rejected',
                'title_en' => 'Offer Rejected',
                'title_ar' => 'تم رفض العرض',
                'message_en' => 'Your offer for {listing_title} has been rejected.',
                'message_ar' => 'تم رفض عرضك لـ {listing_title}.',
            ],
        ];

        foreach ($templates as $data) {
            NotificationTemplate::updateOrCreate(
                ['type' => $data['type']],
                [
                    'name' => ucfirst(str_replace('_', ' ', $data['type'])),
                    'title_template' => $data['title_en'],
                    'title_template_ar' => $data['title_ar'],
                    'message_template' => $data['message_en'],
                    'message_template_ar' => $data['message_ar'],
                    'is_active' => true,
                    'icon' => 'notifications',
                    'color' => '#FF6B6B',
                    'variables' => json_encode(['amount', 'listing_title', 'buyer_name']), // Generic variables
                ]
            );
        }
    }
}
