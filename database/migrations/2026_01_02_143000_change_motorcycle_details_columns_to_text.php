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
        Schema::table('motorcycle_details', function (Blueprint $table) {
            $table->text('engine_details')->nullable()->change();
            $table->text('fuel_system')->nullable()->change();
            $table->text('gearbox')->nullable()->change();
            $table->text('transmission_type')->nullable()->change();
            $table->text('front_suspension')->nullable()->change();
            $table->text('rear_suspension')->nullable()->change();
            $table->text('front_tire')->nullable()->change();
            $table->text('rear_tire')->nullable()->change();
            $table->text('front_brakes')->nullable()->change();
            $table->text('rear_brakes')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('motorcycle_details', function (Blueprint $table) {
            $table->string('engine_details')->nullable()->change();
            $table->string('fuel_system')->nullable()->change();
            $table->string('gearbox')->nullable()->change();
            $table->string('transmission_type')->nullable()->change();
            $table->string('front_suspension')->nullable()->change();
            $table->string('rear_suspension')->nullable()->change();
            $table->string('front_tire')->nullable()->change();
            $table->string('rear_tire')->nullable()->change();
            $table->string('front_brakes')->nullable()->change();
            $table->string('rear_brakes')->nullable()->change();
        });
    }
};
