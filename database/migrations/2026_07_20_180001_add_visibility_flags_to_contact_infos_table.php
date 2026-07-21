<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_infos', function (Blueprint $table) {
            $table->boolean('address_visible')->default(true)->after('address_ar');
            $table->boolean('phone_visible')->default(true)->after('phone');
            $table->boolean('email_visible')->default(true)->after('email');
            $table->boolean('hours_visible')->default(true)->after('hours_ar');
        });
    }

    public function down(): void
    {
        Schema::table('contact_infos', function (Blueprint $table) {
            $table->dropColumn(['address_visible', 'phone_visible', 'email_visible', 'hours_visible']);
        });
    }
};
