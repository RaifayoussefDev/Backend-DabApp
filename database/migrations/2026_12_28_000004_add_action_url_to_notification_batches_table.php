<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional deep-link / redirect target carried in the broadcast's push `data`
 * payload, so tapping the notification opens a specific screen instead of just
 * the app home. Applies to both the user and guest audiences.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_batches', function (Blueprint $table) {
            $table->string('action_url')->nullable()->after('body_ar');
        });
    }

    public function down(): void
    {
        Schema::table('notification_batches', function (Blueprint $table) {
            $table->dropColumn('action_url');
        });
    }
};
