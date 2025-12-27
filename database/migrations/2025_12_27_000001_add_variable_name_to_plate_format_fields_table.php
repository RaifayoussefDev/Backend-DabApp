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
        Schema::table('plate_format_fields', function (Blueprint $table) {
            $table->string('variable_name')->nullable()->after('field_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plate_format_fields', function (Blueprint $table) {
            $table->dropColumn('variable_name');
        });
    }
};
