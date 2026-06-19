<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Aligns the pre-existing trainer_gallery table to the new schema:
// renames photo → path, order → sort_order
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_gallery', function (Blueprint $table) {
            // Rename photo → path if photo exists and path does not
            $columns = array_column(DB::select('SHOW COLUMNS FROM trainer_gallery'), 'Field');

            if (in_array('photo', $columns) && !in_array('path', $columns)) {
                $table->renameColumn('photo', 'path');
            }

            if (in_array('order', $columns) && !in_array('sort_order', $columns)) {
                $table->renameColumn('order', 'sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trainer_gallery', function (Blueprint $table) {
            $columns = array_column(DB::select('SHOW COLUMNS FROM trainer_gallery'), 'Field');

            if (in_array('path', $columns) && !in_array('photo', $columns)) {
                $table->renameColumn('path', 'photo');
            }

            if (in_array('sort_order', $columns) && !in_array('order', $columns)) {
                $table->renameColumn('sort_order', 'order');
            }
        });
    }
};
