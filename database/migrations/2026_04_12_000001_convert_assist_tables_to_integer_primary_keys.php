<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert all Assist module tables from UUID primary keys to auto-increment integers.
     * Also converts UUID foreign-key columns between Assist tables to unsignedBigInteger.
     *
     * Truncates all 8 tables first — run only when no production data exists.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // ── 1. Truncate all Assist tables ────────────────────────────────────
        DB::table('assist_notifications')->truncate();
        DB::table('assist_ratings')->truncate();
        DB::table('request_photos')->truncate();
        DB::table('assistance_requests')->truncate();
        DB::table('helper_expertises')->truncate();
        DB::table('assist_motorcycles')->truncate();
        DB::table('expertise_types')->truncate();
        DB::table('helper_profiles')->truncate();

        // ── 2. Drop all Foreign Keys and Unique Indexes first ────────────────
        // Use helpers that check information_schema so they are no-ops when the
        // constraint does not exist (server schema may differ from local).
        $dropFk = function (string $table, string $fkName): void {
            $exists = DB::selectOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME    = ?
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                   AND CONSTRAINT_NAME = ?",
                [$table, $fkName]
            );
            if ($exists) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            }
        };

        $dropIndex = function (string $table, string $indexName): void {
            $exists = DB::selectOne(
                "SELECT INDEX_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME  = ?
                   AND INDEX_NAME  = ?",
                [$table, $indexName]
            );
            if ($exists) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
            }
        };

        $dropFk('helper_expertises', 'helper_expertises_helper_profile_id_foreign');
        $dropFk('helper_expertises', 'helper_expertises_expertise_type_id_foreign');
        $dropIndex('helper_expertises', 'helper_expertises_helper_profile_id_expertise_type_id_unique');

        $dropFk('assistance_requests', 'assistance_requests_motorcycle_id_foreign');
        $dropFk('assistance_requests', 'assistance_requests_expertise_type_id_foreign');

        $dropFk('request_photos', 'request_photos_request_id_foreign');

        $dropFk('assist_ratings', 'assist_ratings_request_id_foreign');
        $dropIndex('assist_ratings', 'assist_ratings_request_id_unique');

        $dropFk('assist_notifications', 'assist_notifications_request_id_foreign');

        // ── 3. Drop Primary Keys and Modify Columns ──────────────────────────
        // Use single ALTER TABLE per table to handle both CHAR(36) and AUTO_INCREMENT PKs:
        // step a) strip AUTO_INCREMENT by modifying without it + drop PK + re-add PK
        // step b) re-apply AUTO_INCREMENT
        // This works regardless of the current column type on the server.

        // helper_profiles
        DB::statement('ALTER TABLE helper_profiles MODIFY COLUMN id BIGINT UNSIGNED NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id), MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // expertise_types
        DB::statement('ALTER TABLE expertise_types MODIFY COLUMN id BIGINT UNSIGNED NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id), MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // helper_expertises
        DB::statement('ALTER TABLE helper_expertises MODIFY COLUMN id BIGINT UNSIGNED NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id), MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE helper_expertises MODIFY COLUMN helper_profile_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE helper_expertises MODIFY COLUMN expertise_type_id BIGINT UNSIGNED NOT NULL');

        // assist_motorcycles
        DB::statement('ALTER TABLE assist_motorcycles MODIFY COLUMN id BIGINT UNSIGNED NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id), MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // assistance_requests
        DB::statement('ALTER TABLE assistance_requests MODIFY COLUMN id BIGINT UNSIGNED NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id), MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE assistance_requests MODIFY COLUMN motorcycle_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE assistance_requests MODIFY COLUMN expertise_type_id BIGINT UNSIGNED NOT NULL');

        // request_photos
        DB::statement('ALTER TABLE request_photos MODIFY COLUMN id BIGINT UNSIGNED NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id), MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE request_photos MODIFY COLUMN request_id BIGINT UNSIGNED NOT NULL');

        // assist_ratings
        DB::statement('ALTER TABLE assist_ratings MODIFY COLUMN id BIGINT UNSIGNED NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id), MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE assist_ratings MODIFY COLUMN request_id BIGINT UNSIGNED NOT NULL');

        // assist_notifications
        DB::statement('ALTER TABLE assist_notifications MODIFY COLUMN id BIGINT UNSIGNED NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id), MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE assist_notifications MODIFY COLUMN request_id BIGINT UNSIGNED NULL');

        // ── 4. Re-add Foreign Keys ───────────────────────────────────────────
        Schema::table('helper_expertises', function (Blueprint $table) {
            $table->unique(['helper_profile_id', 'expertise_type_id']);
            $table->foreign('helper_profile_id')->references('id')->on('helper_profiles')->cascadeOnDelete();
            $table->foreign('expertise_type_id')->references('id')->on('expertise_types')->cascadeOnDelete();
        });

        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->foreign('motorcycle_id')->references('id')->on('assist_motorcycles')->nullOnDelete();
            $table->foreign('expertise_type_id')->references('id')->on('expertise_types');
        });

        Schema::table('request_photos', function (Blueprint $table) {
            $table->foreign('request_id')->references('id')->on('assistance_requests')->cascadeOnDelete();
        });

        Schema::table('assist_ratings', function (Blueprint $table) {
            $table->unique('request_id');
            $table->foreign('request_id')->references('id')->on('assistance_requests')->cascadeOnDelete();
        });

        Schema::table('assist_notifications', function (Blueprint $table) {
            $table->foreign('request_id')->references('id')->on('assistance_requests')->nullOnDelete();
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse: restore UUID primary keys (structural only — data is truncated).
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('assist_notifications')->truncate();
        DB::table('assist_ratings')->truncate();
        DB::table('request_photos')->truncate();
        DB::table('assistance_requests')->truncate();
        DB::table('helper_expertises')->truncate();
        DB::table('assist_motorcycles')->truncate();
        DB::table('expertise_types')->truncate();
        DB::table('helper_profiles')->truncate();

        // Drop all current FKs (safe — skips any that don't exist on this server)
        $dropFk = function (string $table, string $fkName): void {
            $exists = DB::selectOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME    = ?
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                   AND CONSTRAINT_NAME = ?",
                [$table, $fkName]
            );
            if ($exists) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            }
        };

        $dropIndex = function (string $table, string $indexName): void {
            $exists = DB::selectOne(
                "SELECT INDEX_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME  = ?
                   AND INDEX_NAME  = ?",
                [$table, $indexName]
            );
            if ($exists) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
            }
        };

        $dropFk('assist_notifications', 'assist_notifications_request_id_foreign');
        $dropFk('assist_ratings', 'assist_ratings_request_id_foreign');
        $dropIndex('assist_ratings', 'assist_ratings_request_id_unique');
        $dropFk('request_photos', 'request_photos_request_id_foreign');
        $dropFk('assistance_requests', 'assistance_requests_motorcycle_id_foreign');
        $dropFk('assistance_requests', 'assistance_requests_expertise_type_id_foreign');
        $dropFk('helper_expertises', 'helper_expertises_helper_profile_id_foreign');
        $dropFk('helper_expertises', 'helper_expertises_expertise_type_id_foreign');
        $dropIndex('helper_expertises', 'helper_expertises_helper_profile_id_expertise_type_id_unique');

        // Restore types and PKs (drop AUTO_INCREMENT first, then swap type + PK)
        foreach ([
            'assist_notifications', 'assist_ratings', 'request_photos',
            'assistance_requests', 'helper_expertises',
            'assist_motorcycles', 'expertise_types', 'helper_profiles',
        ] as $tbl) {
            DB::statement("ALTER TABLE `{$tbl}` MODIFY COLUMN id BIGINT UNSIGNED NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id)");
            DB::statement("ALTER TABLE `{$tbl}` MODIFY COLUMN id CHAR(36) NOT NULL");
        }

        DB::statement('ALTER TABLE assist_notifications MODIFY COLUMN request_id CHAR(36) NULL');
        DB::statement('ALTER TABLE assist_ratings MODIFY COLUMN request_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE request_photos MODIFY COLUMN request_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE assistance_requests MODIFY COLUMN motorcycle_id CHAR(36) NULL');
        DB::statement('ALTER TABLE assistance_requests MODIFY COLUMN expertise_type_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE helper_expertises MODIFY COLUMN helper_profile_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE helper_expertises MODIFY COLUMN expertise_type_id CHAR(36) NOT NULL');

        // Re-add FKs
        Schema::table('assist_notifications', function (Blueprint $table) { $table->foreign('request_id')->references('id')->on('assistance_requests')->nullOnDelete(); });
        Schema::table('assist_ratings', function (Blueprint $table) { $table->unique('request_id'); $table->foreign('request_id')->references('id')->on('assistance_requests')->cascadeOnDelete(); });
        Schema::table('request_photos', function (Blueprint $table) { $table->foreign('request_id')->references('id')->on('assistance_requests')->cascadeOnDelete(); });
        Schema::table('assistance_requests', function (Blueprint $table) { $table->foreign('motorcycle_id')->references('id')->on('assist_motorcycles')->nullOnDelete(); $table->foreign('expertise_type_id')->references('id')->on('expertise_types'); });
        Schema::table('helper_expertises', function (Blueprint $table) { $table->unique(['helper_profile_id', 'expertise_type_id']); $table->foreign('helper_profile_id')->references('id')->on('helper_profiles')->cascadeOnDelete(); $table->foreign('expertise_type_id')->references('id')->on('expertise_types')->cascadeOnDelete(); });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
