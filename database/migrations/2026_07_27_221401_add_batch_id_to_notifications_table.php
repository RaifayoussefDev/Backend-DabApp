<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('id')
                ->constrained('notification_batches')->nullOnDelete();
            $table->index(['batch_id', 'is_read'], 'idx_batch_read');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_batch_read');
            $table->dropConstrainedForeignId('batch_id');
        });
    }
};
