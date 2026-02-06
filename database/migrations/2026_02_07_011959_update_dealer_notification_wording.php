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
        DB::table('notification_templates')
            ->where('type', 'dealer_approved')
            ->update([
                'name' => 'Dealer Status Activated',
                'title_template' => 'Dealer Status Activated',
                'title_template_ar' => 'تم تفعيل حساب التاجر',
                'message_template' => 'Your account has been upgraded to Dealer status.',
                'message_template_ar' => 'تمت ترقية حسابك إلى وضع التاجر.',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('notification_templates')
            ->where('type', 'dealer_approved')
            ->update([
                'name' => 'Dealer Approved',
                'title_template' => 'Dealer Status Approved',
                'title_template_ar' => 'تمت الموافقة على حالة التاجر',
                'message_template' => 'Congratulations! Your account has been upgraded to Dealer status.',
                'message_template_ar' => 'تهانينا! تمت ترقية حسابك إلى وضع التاجر.',
                'updated_at' => now(),
            ]);
    }
};
