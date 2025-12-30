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
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'start_date')) {
                $table->date('start_date')->nullable()->after('event_date');
            }
            if (!Schema::hasColumn('events', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });

        Schema::table('event_activities', function (Blueprint $table) {
            if (!Schema::hasColumn('event_activities', 'day_in_event')) {
                $table->integer('day_in_event')->nullable()->after('location');
            }
            if (!Schema::hasColumn('event_activities', 'title_ar')) {
                $table->string('title_ar')->nullable()->after('title');
            }
            if (!Schema::hasColumn('event_activities', 'description_ar')) {
                $table->text('description_ar')->nullable()->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });

        Schema::table('event_activities', function (Blueprint $table) {
            $table->dropColumn(['day_in_event', 'title_ar', 'description_ar']);
        });
    }
};
