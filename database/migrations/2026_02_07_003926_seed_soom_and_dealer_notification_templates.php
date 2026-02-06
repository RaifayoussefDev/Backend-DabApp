<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $templates = [
            [
                'type' => 'dealer_approved',
                'name' => 'Dealer Approved',
                'title_template' => 'Dealer Status Approved',
                'title_template_ar' => 'تمت الموافقة sur le statut de revendeur',
                'message_template' => 'Congratulations! Your account has been upgraded to Dealer status.',
                'message_template_ar' => 'تهانينا! تمت ترقية حسابك إلى وضع التاجر.',
                'icon' => 'verified_user',
                'color' => '#4CAF50',
                'is_active' => true,
                'variables' => json_encode(['admin_id']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'soom_new_negotiation',
                'name' => 'Soom New Negotiation',
                'title_template' => 'New Offer Received',
                'title_template_ar' => 'عرض جديد تم استلامه',
                'message_template' => 'You have received a new offer of {amount} for {listing_title}.',
                'message_template_ar' => 'لقد تلقيت عرضًا جديدًا بقيمة {amount} لـ {listing_title}.',
                'icon' => 'monetization_on',
                'color' => '#2196F3',
                'is_active' => true,
                'variables' => json_encode(['buyer_name', 'listing_title', 'amount']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'soom_accepted',
                'name' => 'Soom Accepted',
                'title_template' => 'Offer Accepted',
                'title_template_ar' => 'تم قبول العرض',
                'message_template' => 'Your offer for {listing_title} has been accepted!',
                'message_template_ar' => 'تم قبول عرضك لـ {listing_title}!',
                'icon' => 'check_circle',
                'color' => '#4CAF50',
                'is_active' => true,
                'variables' => json_encode(['listing_title', 'amount', 'currency']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'soom_rejected',
                'name' => 'Soom Rejected',
                'title_template' => 'Offer Rejected',
                'title_template_ar' => 'تم رفض العرض',
                'message_template' => 'Your offer for {listing_title} has been rejected.',
                'message_template_ar' => 'تم رفض عرضك لـ {listing_title}.',
                'icon' => 'cancel',
                'color' => '#F44336',
                'is_active' => true,
                'variables' => json_encode(['listing_title', 'reason']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($templates as $template) {
            // Use updateOrInsert to avoid duplicates or errors if they already exist
            DB::table('notification_templates')->updateOrInsert(
                ['type' => $template['type']],
                $template
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $types = ['dealer_approved', 'soom_new_negotiation', 'soom_accepted', 'soom_rejected'];
        DB::table('notification_templates')->whereIn('type', $types)->delete();
    }
};
