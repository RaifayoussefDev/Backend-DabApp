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

        // ── 2. Drop all Foreign Keys first ───────────────────────────────────
        // These must be dropped before we change the column types they reference.
        Schema::table('helper_expertises', function (Blueprint $table) {
            $table->dropForeign(['helper_profile_id']);
            $table->dropForeign(['expertise_type_id']);
            $table->dropUnique(['helper_profile_id', 'expertise_type_id']);
        });

        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->dropForeign(['motorcycle_id']);
            $table->dropForeign(['expertise_type_id']);
        });

        Schema::table('request_photos', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
        });

        Schema::table('assist_ratings', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
            $table->dropUnique(['request_id']);
        });

        Schema::table('assist_notifications', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
        });

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

        // Drop all current FKs
        Schema::table('assist_notifications', function (Blueprint $table) { $table->dropForeign(['request_id']); });
        Schema::table('assist_ratings', function (Blueprint $table) { $table->dropForeign(['request_id']); $table->dropUnique(['request_id']); });
        Schema::table('request_photos', function (Blueprint $table) { $table->dropForeign(['request_id']); });
        Schema::table('assistance_requests', function (Blueprint $table) { $table->dropForeign(['motorcycle_id']); $table->dropForeign(['expertise_type_id']); });
        Schema::table('helper_expertises', function (Blueprint $table) { $table->dropForeign(['helper_profile_id']); $table->dropForeign(['expertise_type_id']); $table->dropUnique(['helper_profile_id', 'expertise_type_id']); });

        // Restore types and PKs
        DB::statement('ALTER TABLE assist_notifications MODIFY COLUMN id CHAR(36) NOT NULL PRIMARY KEY');
        DB::statement('ALTER TABLE assist_notifications MODIFY COLUMN request_id CHAR(36) NULL');

        DB::statement('ALTER TABLE assist_ratings MODIFY COLUMN id CHAR(36) NOT NULL PRIMARY KEY');
        DB::statement('ALTER TABLE assist_ratings MODIFY COLUMN request_id CHAR(36) NOT NULL');

        DB::statement('ALTER TABLE request_photos MODIFY COLUMN id CHAR(36) NOT NULL PRIMARY KEY');
        DB::statement('ALTER TABLE request_photos MODIFY COLUMN request_id CHAR(36) NOT NULL');

        DB::statement('ALTER TABLE assistance_requests MODIFY COLUMN id CHAR(36) NOT NULL PRIMARY KEY');
        DB::statement('ALTER TABLE assistance_requests MODIFY COLUMN motorcycle_id CHAR(36) NULL');
        DB::statement('ALTER TABLE assistance_requests MODIFY COLUMN expertise_type_id CHAR(36) NOT NULL');

        DB::statement('ALTER TABLE helper_expertises MODIFY COLUMN id CHAR(36) NOT NULL PRIMARY KEY');
        DB::statement('ALTER TABLE helper_expertises MODIFY COLUMN helper_profile_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE helper_expertises MODIFY COLUMN expertise_type_id CHAR(36) NOT NULL');

        DB::statement('ALTER TABLE assist_motorcycles MODIFY COLUMN id CHAR(36) NOT NULL PRIMARY KEY');
        DB::statement('ALTER TABLE expertise_types MODIFY COLUMN id CHAR(36) NOT NULL PRIMARY KEY');
        DB::statement('ALTER TABLE helper_profiles MODIFY COLUMN id CHAR(36) NOT NULL PRIMARY KEY');

        // Re-add FKs
        Schema::table('assist_notifications', function (Blueprint $table) { $table->foreign('request_id')->references('id')->on('assistance_requests')->nullOnDelete(); });
        Schema::table('assist_ratings', function (Blueprint $table) { $table->unique('request_id'); $table->foreign('request_id')->references('id')->on('assistance_requests')->cascadeOnDelete(); });
        Schema::table('request_photos', function (Blueprint $table) { $table->foreign('request_id')->references('id')->on('assistance_requests')->cascadeOnDelete(); });
        Schema::table('assistance_requests', function (Blueprint $table) { $table->foreign('motorcycle_id')->references('id')->on('assist_motorcycles')->nullOnDelete(); $table->foreign('expertise_type_id')->references('id')->on('expertise_types'); });
        Schema::table('helper_expertises', function (Blueprint $table) { $table->unique(['helper_profile_id', 'expertise_type_id']); $table->foreign('helper_profile_id')->references('id')->on('helper_profiles')->cascadeOnDelete(); $table->foreign('expertise_type_id')->references('id')->on('expertise_types')->cascadeOnDelete(); });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
