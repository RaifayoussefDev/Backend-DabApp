<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            // Vérification et ajout de la colonne 'message' qui semble manquer aussi
            if (!Schema::hasColumn('notification_templates', 'message')) {
                // On essaie de placer 'message' après 'title' si 'title' existe, sinon après 'type'
                $after = Schema::hasColumn('notification_templates', 'title') ? 'title' : 'type';
                $table->text('message')->nullable()->after($after);
            }
            
            // Vérification de sécurité pour les autres colonnes potentiellement manquantes
            if (!Schema::hasColumn('notification_templates', 'title')) {
                $table->string('title')->nullable()->after('type');
            }
        });
    }

    public function down()
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            // Pas de rollback destructif
        });
    }
};
