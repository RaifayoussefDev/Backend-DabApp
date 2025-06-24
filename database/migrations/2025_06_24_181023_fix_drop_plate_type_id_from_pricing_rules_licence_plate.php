<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop foreign key if it exists
        $foreignKeyName = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->select('CONSTRAINT_NAME')
            ->where('TABLE_NAME', 'pricing_rules_licence_plate')
            ->where('COLUMN_NAME', 'plate_type_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->value('CONSTRAINT_NAME');

        if ($foreignKeyName) {
            DB::statement("ALTER TABLE pricing_rules_licence_plate DROP FOREIGN KEY `$foreignKeyName`");
        }

        // Drop column if it exists
        if (Schema::hasColumn('pricing_rules_licence_plate', 'plate_type_id')) {
            Schema::table('pricing_rules_licence_plate', function (Blueprint $table) {
                $table->dropColumn('plate_type_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pricing_rules_licence_plate', function (Blueprint $table) {
            $table->unsignedBigInteger('plate_type_id')->nullable();
            $table->foreign('plate_type_id')->references('id')->on('plate_types')->onDelete('cascade');
        });
    }
};

