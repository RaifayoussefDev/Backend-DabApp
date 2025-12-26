<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_preferences', 'soom_new_negotiation')) {
                $table->boolean('soom_new_negotiation')->default(true)->after('bid_outbid');
            }
            if (!Schema::hasColumn('notification_preferences', 'soom_counter_offer')) {
                $table->boolean('soom_counter_offer')->default(true)->after('soom_new_negotiation');
            }
            if (!Schema::hasColumn('notification_preferences', 'soom_accepted')) {
                $table->boolean('soom_accepted')->default(true)->after('soom_counter_offer');
            }
            if (!Schema::hasColumn('notification_preferences', 'soom_rejected')) {
                $table->boolean('soom_rejected')->default(true)->after('soom_accepted');
            }
        });
    }

    public function down()
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'soom_new_negotiation',
                'soom_counter_offer',
                'soom_accepted',
                'soom_rejected'
            ]);
        });
    }
};
