<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->string('title_template_ar')->nullable()->after('title_template');
            $table->text('message_template_ar')->nullable()->after('message_template');
            $table->text('email_template_ar')->nullable()->after('email_template');
            $table->text('sms_template_ar')->nullable()->after('sms_template');
        });
    }

    public function down()
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropColumn([
                'title_template_ar',
                'message_template_ar',
                'email_template_ar',
                'sms_template_ar'
            ]);
        });
    }
};