<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stable per-install id (header `X-Device-Id`) for guest views, so guest push
 * campaigns can target "devices that viewed category X / listing Y" by joining
 * `views` to `guest_notification_tokens` on `device_id`. IP + user-agent (used
 * for view dedup) is not stable enough to join on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('views', function (Blueprint $table) {
            $table->string('device_id')->nullable()->after('user_agent');
            $table->index(['viewable_type', 'viewable_id', 'device_id'], 'idx_views_viewable_device');
        });
    }

    public function down(): void
    {
        Schema::table('views', function (Blueprint $table) {
            $table->dropIndex('idx_views_viewable_device');
            $table->dropColumn('device_id');
        });
    }
};
