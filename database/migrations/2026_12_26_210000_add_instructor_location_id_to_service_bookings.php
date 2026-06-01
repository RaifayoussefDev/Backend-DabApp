<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->foreignId('instructor_location_id')
                  ->nullable()
                  ->after('provider_id')
                  ->constrained('instructor_locations')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->dropForeign(['instructor_location_id']);
            $table->dropColumn('instructor_location_id');
        });
    }
};
