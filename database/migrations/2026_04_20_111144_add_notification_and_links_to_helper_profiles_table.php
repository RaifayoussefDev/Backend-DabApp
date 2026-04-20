<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('helper_profiles', function (Blueprint $table) {
            // Notification preferences
            $table->boolean('notify_push')->default(true)->after('terms_accepted_at');
            $table->boolean('notify_whatsapp')->default(false)->after('notify_push');
            $table->boolean('notify_email')->default(false)->after('notify_whatsapp');

            // Verification / social links
            $table->string('instagram_url')->nullable()->after('notify_email');
            $table->string('facebook_url')->nullable()->after('instagram_url');
            $table->string('linkedin_url')->nullable()->after('facebook_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('helper_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'notify_push', 'notify_whatsapp', 'notify_email',
                'instagram_url', 'facebook_url', 'linkedin_url',
            ]);
        });
    }
};
