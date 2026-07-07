<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop orphaned single-select columns from an earlier, superseded design
        // (equipment/bikes are now a many-to-many selection via pivot tables).
        if (Schema::hasColumn('trainer_courses', 'training_bike_id')) {
            Schema::table('trainer_courses', function (Blueprint $table) {
                $table->dropForeign(['training_bike_id']);
            });
            Schema::table('trainer_courses', function (Blueprint $table) {
                $table->dropColumn('training_bike_id');
            });
        }

        if (Schema::hasColumn('trainer_courses', 'equipment_type_ids')) {
            Schema::table('trainer_courses', function (Blueprint $table) {
                $table->dropColumn('equipment_type_ids');
            });
        }

        if (Schema::hasColumn('trainer_courses', 'session_date')) {
            Schema::table('trainer_courses', function (Blueprint $table) {
                $table->renameColumn('session_date', 'start_date');
            });
        }

        // Backfill any course left without a location (from before location_id was
        // required) using its trainer's first location, so the NOT NULL change below succeeds.
        DB::table('trainer_courses')->whereNull('location_id')->orderBy('id')->get()->each(function ($course) {
            $locationId = DB::table('trainer_locations')->where('trainer_id', $course->trainer_id)->orderBy('id')->value('id');
            if ($locationId) {
                DB::table('trainer_courses')->where('id', $course->id)->update(['location_id' => $locationId]);
            }
        });

        // location_id becomes mandatory — the "set null on delete" FK action is no
        // longer valid on a NOT NULL column, so the constraint is recreated as restrict.
        Schema::table('trainer_courses', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
        });

        Schema::table('trainer_courses', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable(false)->change();
        });

        Schema::table('trainer_courses', function (Blueprint $table) {
            $table->foreign('location_id')->references('id')->on('trainer_locations')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainer_courses', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
        });

        Schema::table('trainer_courses', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->change();
        });

        Schema::table('trainer_courses', function (Blueprint $table) {
            $table->foreign('location_id')->references('id')->on('trainer_locations')->nullOnDelete();
        });

        Schema::table('trainer_courses', function (Blueprint $table) {
            $table->renameColumn('start_date', 'session_date');
        });
    }
};
