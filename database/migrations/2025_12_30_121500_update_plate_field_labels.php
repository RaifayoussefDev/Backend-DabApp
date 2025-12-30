<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // KSA Format (ID 58 based on user input, or we filter by variable names for KSA structure)
        // We use variable_name + plate_format_id logic to be safe, or just variable_name if unique per format type.
        // Given the user provided specific IDs, we can also try to target them if we are sure, 
        // but it's better to target by format properties if possible.
        // Let's assume the user provided IDs 206-209 are for KSA, and 210-213 for UAE/Dubai.
        
        // KSA Updates
        $ksaUpdates = [
            'top_left' => ['en' => 'Digits (Arabic)', 'ar' => 'الأرقام (عربي)'],
            'top_right' => ['en' => 'Letters (Arabic)', 'ar' => 'الحروف (عربي)'],
            'bottom_left' => ['en' => 'Letters (English)', 'ar' => 'الحروف (انجليزي)'],
            'bottom_right' => ['en' => 'Digits (English)', 'ar' => 'الأرقام (انجليزي)'],
        ];

        foreach ($ksaUpdates as $var => $labels) {
            DB::table('plate_format_fields')
                ->where('variable_name', $var)
                ->where('plate_format_id', 58) 
                ->update([
                    'field_name' => $labels['en'],
                    'field_name_ar' => $labels['ar']
                ]);
        }

        // UAE/Dubai Updates (IDs 59 and 60)
        $uaeUpdates = [
            'category_number' => ['en' => 'Code', 'ar' => 'الرمز'],
            'plate_number' => ['en' => 'Plate Number', 'ar' => 'رقم اللوحة'],
        ];

        foreach ($uaeUpdates as $var => $labels) {
            DB::table('plate_format_fields')
                ->where('variable_name', $var)
                ->whereIn('plate_format_id', [59, 60])
                ->update([
                    'field_name' => $labels['en'],
                    'field_name_ar' => $labels['ar']
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to variable names (or previous names if we knew them, but here we just revert to variable_name as a fallback)
        // KSA
        DB::table('plate_format_fields')
            ->where('plate_format_id', 58)
            ->update([
                'field_name' => DB::raw('variable_name'),
                'field_name_ar' => null
            ]);

        // UAE/Dubai
        DB::table('plate_format_fields')
            ->whereIn('plate_format_id', [59, 60])
            ->update([
                'field_name' => DB::raw('variable_name'),
                'field_name_ar' => null
            ]);
    }
};
