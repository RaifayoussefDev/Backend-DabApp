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
            // Ajouter les nouveaux champs seulement s'ils n'existent pas déjà
            if (!Schema::hasColumn('submissions', 'sale_validated')) {
                $table->boolean('sale_validated')->default(false)->after('acceptance_date');
            }

            if (!Schema::hasColumn('submissions', 'sale_validation_date')) {
                $table->timestamp('sale_validation_date')->nullable()->after('sale_validated');
            }

            if (!Schema::hasColumn('submissions', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('sale_validation_date');
            }

            // Ajouter les index uniquement si les colonnes existent
            if (Schema::hasColumn('submissions', 'status') && Schema::hasColumn('submissions', 'sale_validated')) {
                $table->index(['status', 'sale_validated'], 'submissions_status_sale_validated_index');
            }

            if (Schema::hasColumn('submissions', 'acceptance_date')) {
                $table->index('acceptance_date', 'submissions_acceptance_date_index');
            }

            if (Schema::hasColumn('submissions', 'sale_validation_date')) {
                $table->index('sale_validation_date', 'submissions_sale_validation_date_index');
            }
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
            // Supprimer les index (avec leurs noms explicites)
            $table->dropIndex('submissions_status_sale_validated_index');
            $table->dropIndex('submissions_acceptance_date_index');
            $table->dropIndex('submissions_sale_validation_date_index');

            // Supprimer seulement les colonnes ajoutées par cette migration
            $columns = ['sale_validated', 'sale_validation_date', 'rejection_reason'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('submissions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
