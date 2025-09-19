<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            // Colonnes
            if (!Schema::hasColumn('submissions', 'sale_validated')) {
                $table->boolean('sale_validated')->default(false)->after('acceptance_date');
            }

            if (!Schema::hasColumn('submissions', 'sale_validation_date')) {
                $table->timestamp('sale_validation_date')->nullable()->after('sale_validated');
            }

            if (!Schema::hasColumn('submissions', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('sale_validation_date');
            }
        });

        // Index (à vérifier via INFORMATION_SCHEMA)
        if (!$this->indexExists('submissions', 'submissions_status_sale_validated_index')) {
            Schema::table('submissions', function (Blueprint $table) {
                $table->index(['status', 'sale_validated'], 'submissions_status_sale_validated_index');
            });
        }

        if (!$this->indexExists('submissions', 'submissions_acceptance_date_index')) {
            Schema::table('submissions', function (Blueprint $table) {
                $table->index('acceptance_date', 'submissions_acceptance_date_index');
            });
        }

        if (!$this->indexExists('submissions', 'submissions_sale_validation_date_index')) {
            Schema::table('submissions', function (Blueprint $table) {
                $table->index('sale_validation_date', 'submissions_sale_validation_date_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('submissions', function (Blueprint $table) {
            if ($this->indexExists('submissions', 'submissions_status_sale_validated_index')) {
                $table->dropIndex('submissions_status_sale_validated_index');
            }

            if ($this->indexExists('submissions', 'submissions_acceptance_date_index')) {
                $table->dropIndex('submissions_acceptance_date_index');
            }

            if ($this->indexExists('submissions', 'submissions_sale_validation_date_index')) {
                $table->dropIndex('submissions_sale_validation_date_index');
            }

            $columns = ['sale_validated', 'sale_validation_date', 'rejection_reason'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('submissions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /**
     * Vérifie si un index existe dans une table.
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();
        $result = DB::selectOne("
            SELECT COUNT(1) AS count
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE table_schema = ? AND table_name = ? AND index_name = ?
        ", [$database, $table, $indexName]);

        return $result->count > 0;
    }
}
