<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_templates', 'title')) {
                $table->string('title')->nullable()->after('type');
            }
            if (!Schema::hasColumn('notification_templates', 'icon')) {
                $table->string('icon')->nullable()->after('message');
            }
            if (!Schema::hasColumn('notification_templates', 'color')) {
                $table->string('color')->nullable()->after('icon');
            }
            if (!Schema::hasColumn('notification_templates', 'sound')) {
                $table->string('sound')->default('default')->after('color');
            }
            if (!Schema::hasColumn('notification_templates', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sound');
            }
        });
    }

    public function down()
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            // On ne supprime pas les colonnes pour éviter la perte de données accidentelle
            // lors d'un rollback complet, mais on pourrait le faire si nécessaire.
        });
    }
};
