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
        // 1. Add new column if it doesn't exist (safety for failed previous run)
        if (!Schema::hasColumn('motorcycles', 'vehicle_care_other')) {
            Schema::table('motorcycles', function (Blueprint $table) {
                $table->string('vehicle_care_other')->nullable()->after('vehicle_care');
            });
        }

        // 2. Temporarily change vehicle_care to VARCHAR to allow data manipulation
        // This avoids "Data truncated" error and allows us to set values not currently in the Enum
        \DB::statement("ALTER TABLE motorcycles MODIFY COLUMN vehicle_care VARCHAR(255) NULL");

        // 3. Migrate existing data: 'Customs License' -> 'Other' + details
        \DB::table('motorcycles')
            ->where('vehicle_care', 'Customs License')
            ->update([
                'vehicle_care' => 'Other',
                'vehicle_care_other' => 'Customs License'
            ]);

        // 4. Apply the new ENUM definition
        \DB::statement("ALTER TABLE motorcycles MODIFY COLUMN vehicle_care ENUM('Wakeel', 'USA', 'Europe', 'GCC', 'Other') NULL");
    }

    public function down(): void
    {
        // 1. Revert data (optional, best effort)
        // We select rows where vehicle_care is 'Other' AND valid 'Customs License' detail was preserved
        \DB::table('motorcycles')
            ->where('vehicle_care', 'Other')
            ->where('vehicle_care_other', 'Customs License')
            ->update(['vehicle_care' => 'Customs License']);

        // 2. Revert ENUM definition (will include Customs License)
        // Note: This might fail if we have other 'Other' values that aren't 'Customs License'. 
        // We temporarily relax to VARCHAR again to be safe.
        \DB::statement("ALTER TABLE motorcycles MODIFY COLUMN vehicle_care VARCHAR(255) NULL");

        \DB::statement("ALTER TABLE motorcycles MODIFY COLUMN vehicle_care ENUM('Wakeel', 'USA', 'Europe', 'GCC', 'Customs License') NULL");

        // 3. Drop column
        if (Schema::hasColumn('motorcycles', 'vehicle_care_other')) {
            Schema::table('motorcycles', function (Blueprint $table) {
                $table->dropColumn('vehicle_care_other');
            });
        }
    }
};
