<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'title_ar')) {
                $table->string('title_ar')->nullable()->after('title');
            }
            if (!Schema::hasColumn('events', 'description_ar')) {
                $table->text('description_ar')->nullable()->after('description');
            }
            if (!Schema::hasColumn('events', 'short_description_ar')) {
                $table->text('short_description_ar')->nullable()->after('short_description');
            }
            if (!Schema::hasColumn('events', 'venue_name_ar')) {
                $table->string('venue_name_ar')->nullable()->after('venue_name');
            }
            if (!Schema::hasColumn('events', 'address_ar')) {
                $table->text('address_ar')->nullable()->after('address');
            }
        });

        Schema::table('event_faqs', function (Blueprint $table) {
            if (!Schema::hasColumn('event_faqs', 'question_ar')) {
                $table->text('question_ar')->nullable()->after('question');
            }
            if (!Schema::hasColumn('event_faqs', 'answer_ar')) {
                $table->text('answer_ar')->nullable()->after('answer');
            }
        });

        // Also checking event_activities for other fields just in case, though checked 2025_12_29
        Schema::table('event_activities', function (Blueprint $table) {
            if (!Schema::hasColumn('event_activities', 'location_ar')) {
                $table->string('location_ar')->nullable()->after('location');
            }
        });

        Schema::table('event_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('event_contacts', 'name_ar')) {
                $table->string('name_ar')->nullable()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'title_ar',
                'description_ar',
                'short_description_ar',
                'venue_name_ar',
                'address_ar'
            ]);
        });

        Schema::table('event_faqs', function (Blueprint $table) {
            $table->dropColumn(['question_ar', 'answer_ar']);
        });

        Schema::table('event_activities', function (Blueprint $table) {
            $table->dropColumn(['location_ar']);
        });

        Schema::table('event_contacts', function (Blueprint $table) {
            $table->dropColumn(['name_ar']);
        });
    }
};
