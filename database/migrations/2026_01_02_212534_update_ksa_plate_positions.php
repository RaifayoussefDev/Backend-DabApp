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
        // Update positions for KSA Plate Config (ID 58)
        // User Request:
        // bottom left : digit latin (Digits (English))
        // bottom right : alphabet latin (Letters (English))
        
        // Currently (from user screenshot):
        // Digits (English) is bottom_right
        // Letters (English) is bottom_left

        DB::table('plate_format_fields')
            ->where('plate_format_id', 58)
            ->where('field_name', 'Digits (English)')
            ->update(['position' => 'bottom_left']);

        DB::table('plate_format_fields')
            ->where('plate_format_id', 58)
            ->where('field_name', 'Letters (English)')
            ->update(['position' => 'bottom_right']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original state
        DB::table('plate_format_fields')
            ->where('plate_format_id', 58)
            ->where('field_name', 'Digits (English)')
            ->update(['position' => 'bottom_right']);

        DB::table('plate_format_fields')
            ->where('plate_format_id', 58)
            ->where('field_name', 'Letters (English)')
            ->update(['position' => 'bottom_left']);
    }
};
