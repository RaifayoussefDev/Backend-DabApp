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
        DB::table('notification_templates')->updateOrInsert(
            ['type' => 'dealer_removed'],
            [
                'name' => 'Dealer Removed',
                'title_template' => 'Dealer Status Removed',
                'title_template_ar' => 'تمت إزالة حالة التاجر',
                'message_template' => 'Your Dealer status has been removed by an administrator.',
                'message_template_ar' => 'تمت إزالة حالة التاجر الخاصة بك من قبل المسؤول.',
                'icon' => 'person_off',
                'color' => '#F44336',
                'is_active' => true,
                'variables' => json_encode(['admin_id']),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('notification_templates')->where('type', 'dealer_removed')->delete();
    }
};
