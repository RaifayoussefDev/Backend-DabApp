<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A broadcast can now target registered users (default, unchanged behaviour),
 * guest devices, or both. `guest_filters` holds the guest-side audience filter
 * (device_type, app_version, country_id, city_id, active_since, viewed_*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_batches', function (Blueprint $table) {
            $table->enum('audience', ['users', 'guests', 'both'])->default('users')->after('type');
            $table->json('guest_filters')->nullable()->after('filters');
        });
    }

    public function down(): void
    {
        Schema::table('notification_batches', function (Blueprint $table) {
            $table->dropColumn(['audience', 'guest_filters']);
        });
    }
};
