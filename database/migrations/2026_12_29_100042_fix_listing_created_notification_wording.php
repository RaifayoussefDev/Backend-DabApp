<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * listing_created is sent when the seller first saves a draft. There is no review
 * step — a listing goes live once the steps (and payment) are done — so the old
 * "under review" wording was wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notification_templates')->where('type', 'listing_created')->update([
            'title_template' => 'Listing saved',
            'title_template_ar' => 'تم حفظ الإعلان',
            'message_template' => 'Your listing "{{listing_title}}" has been saved. Complete the remaining steps to publish it.',
            'message_template_ar' => 'تم حفظ إعلانك "{{listing_title}}". أكمل الخطوات المتبقية لنشره.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('notification_templates')->where('type', 'listing_created')->update([
            'title_template' => 'Listing Created',
            'message_template' => 'Your listing "{{listing_title}}" has been created and is under review',
            'updated_at' => now(),
        ]);
    }
};
