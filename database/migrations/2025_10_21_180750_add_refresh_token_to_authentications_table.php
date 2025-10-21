<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Vérifier si la table s'appelle 'authentication' (singulier) ou 'authentications' (pluriel)
        $tableName = Schema::hasTable('authentication') ? 'authentication' : 'authentications';

        // Ajouter refresh_token si elle n'existe pas
        if (!Schema::hasColumn($tableName, 'refresh_token')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('refresh_token', 100)->nullable()->after('token');
            });
        }

        // Ajouter refresh_token_expiration si elle n'existe pas
        if (!Schema::hasColumn($tableName, 'refresh_token_expiration')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('refresh_token_expiration')->nullable()->after('token_expiration');
            });
        }

        // Ajouter l'index sur refresh_token si la colonne existe
        if (Schema::hasColumn($tableName, 'refresh_token')) {
            // Vérifier si l'index n'existe pas déjà
            if (!$this->indexExists($tableName, 'refresh_token')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->index('refresh_token');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tableName = Schema::hasTable('authentication') ? 'authentication' : 'authentications';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            // Supprimer l'index s'il existe
            if ($this->indexExists($tableName, 'refresh_token')) {
                $table->dropIndex([$tableName . '_refresh_token_index']);
            }

            // Supprimer les colonnes si elles existent
            $columnsToDrop = [];

            if (Schema::hasColumn($tableName, 'refresh_token')) {
                $columnsToDrop[] = 'refresh_token';
            }

            if (Schema::hasColumn($tableName, 'refresh_token_expiration')) {
                $columnsToDrop[] = 'refresh_token_expiration';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * Vérifier si un index existe sur une colonne
     *
     * @param string $tableName
     * @param string $columnName
     * @return bool
     */
    private function indexExists($tableName, $columnName)
    {
        $indexes = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Column_name = ?", [$columnName]);
        return !empty($indexes);
    }
};
