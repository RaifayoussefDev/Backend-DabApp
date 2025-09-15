<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSaleFieldsToSubmissions2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('submissions', function (Blueprint $table) {
            // Ajouter les nouveaux champs pour la gestion des ventes
            $table->timestamp('acceptance_date')->nullable()->after('status');
            $table->boolean('sale_validated')->default(false)->after('acceptance_date');
            $table->timestamp('sale_validation_date')->nullable()->after('sale_validated');
            $table->text('rejection_reason')->nullable()->after('sale_validation_date');

            // Ajouter des index pour améliorer les performances
            $table->index(['status', 'sale_validated']);
            $table->index('acceptance_date');
            $table->index('sale_validation_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('submissions', function (Blueprint $table) {
            // Supprimer les index
            $table->dropIndex(['status', 'sale_validated']);
            $table->dropIndex(['acceptance_date']);
            $table->dropIndex(['sale_validation_date']);

            // Supprimer les colonnes
            $table->dropColumn([
                'acceptance_date',
                'sale_validated',
                'sale_validation_date',
                'rejection_reason'
            ]);
        });
    }
}
