<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per admin spreadsheet upload: status, progress counters and the
 * checkpoint (byte offset in the normalized CSV) the chunk jobs resume from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motorcycle_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('data_path')->nullable();
            // queued | preparing | processing | completed | failed | cancelled
            $table->string('status', 20)->default('queued')->index();
            $table->foreignId('default_type_id')->nullable()->constrained('motorcycle_types')->nullOnDelete();

            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('data_offset')->default(0);

            $table->unsignedInteger('inserted_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('new_brands_count')->default(0);
            $table->unsignedInteger('new_models_count')->default(0);

            $table->json('column_map')->nullable();
            $table->json('mapped_headers')->nullable();
            $table->json('unmapped_headers')->nullable();
            $table->json('row_errors')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motorcycle_imports');
    }
};
