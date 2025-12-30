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
        Schema::table('plate_format_fields', function (Blueprint $table) {
            if (!Schema::hasColumn('plate_format_fields', 'field_name_ar')) {
                $table->string('field_name_ar')->nullable()->after('field_name');
            }
        });

        // Migrate data: Force variable_name to be equal to field_name if variable_name is empty/null
        DB::table('plate_format_fields')
            ->whereNull('variable_name')
            ->orWhere('variable_name', '')
            ->update(['variable_name' => DB::raw('field_name')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plate_format_fields', function (Blueprint $table) {
             if (Schema::hasColumn('plate_format_fields', 'field_name_ar')) {
                $table->dropColumn('field_name_ar');
            }
        });
    }
};
