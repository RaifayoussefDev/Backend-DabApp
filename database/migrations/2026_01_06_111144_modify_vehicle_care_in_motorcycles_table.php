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
        Schema::table('motorcycles', function (Blueprint $table) {
            $table->string('vehicle_care_other')->nullable()->after('vehicle_care');
        });

        // Use raw SQL to modify the ENUM column
        \DB::statement("ALTER TABLE motorcycles MODIFY COLUMN vehicle_care ENUM('Wakeel', 'USA', 'Europe', 'GCC', 'Other') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('motorcycles', function (Blueprint $table) {
            $table->dropColumn('vehicle_care_other');
        });

        // Revert the ENUM column
        \DB::statement("ALTER TABLE motorcycles MODIFY COLUMN vehicle_care ENUM('Wakeel', 'USA', 'Europe', 'GCC', 'Customs License') NULL");
    }
};
