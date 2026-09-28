<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // entity_id was hard-wired to trainers.id; it now points to a trainer, a vendor or a category
        // depending on entity_type, so the foreign key has to go (the column and its index stay).
        Schema::table('commission_settings', function (Blueprint $table) {
            $table->dropForeign(['entity_id']);
        });

        DB::statement("ALTER TABLE commission_settings MODIFY entity_type ENUM('global','trainer','vendor','category') NOT NULL DEFAULT 'global'");
    }

    public function down(): void
    {
        DB::table('commission_settings')->whereIn('entity_type', ['vendor', 'category'])->delete();

        DB::statement("ALTER TABLE commission_settings MODIFY entity_type ENUM('global','trainer') NOT NULL DEFAULT 'global'");

        Schema::table('commission_settings', function (Blueprint $table) {
            $table->foreign('entity_id')->references('id')->on('trainers')->onDelete('cascade');
        });
    }
};
