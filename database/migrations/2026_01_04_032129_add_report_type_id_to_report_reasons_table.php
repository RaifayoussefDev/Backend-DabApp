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
        Schema::table('report_reasons', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->foreignId('report_type_id')->after('id')->constrained('report_types')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_reasons', function (Blueprint $table) {
            $table->string('type')->after('id')->index();
            $table->dropForeign(['report_type_id']);
            $table->dropColumn('report_type_id');
        });
    }
};
