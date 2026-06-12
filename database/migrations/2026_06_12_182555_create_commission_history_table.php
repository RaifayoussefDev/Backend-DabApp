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
        Schema::create('commission_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_setting_id')->constrained('commission_settings')->onDelete('cascade');
            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade');
            $table->decimal('old_percentage', 5, 2);
            $table->decimal('new_percentage', 5, 2);
            $table->text('reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_history');
    }
};
