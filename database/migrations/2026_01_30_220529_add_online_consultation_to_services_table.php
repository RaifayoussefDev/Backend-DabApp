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
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('has_online_consultation')->default(false)->after('image')->comment('Service Maintenance specific');
            $table->decimal('consultation_price_per_session', 10, 2)->nullable()->after('has_online_consultation')->comment('Price per online consultation session');
            $table->string('consultation_email')->nullable()->after('consultation_price_per_session')->comment('Email for online consultations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['has_online_consultation', 'consultation_price_per_session', 'consultation_email']);
        });
    }
};