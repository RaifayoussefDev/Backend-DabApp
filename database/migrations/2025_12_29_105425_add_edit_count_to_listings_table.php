<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->integer('edit_count')->default(0)->after('status')->comment('Number of times listing has been edited');
            $table->timestamp('last_edited_at')->nullable()->after('edit_count')->comment('Last edit timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['edit_count', 'last_edited_at']);
        });
    }
};
