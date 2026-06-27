<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_level_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->onDelete('cascade');
            $table->foreignId('level_id')->constrained('trainer_levels')->onDelete('cascade');
            // What the trainer proposed at registration
            $table->decimal('proposed_price', 10, 2)->nullable();
            // What admin set as final (can differ from proposal)
            $table->decimal('approved_price', 10, 2)->nullable();
            $table->enum('status', ['proposed', 'approved', 'rejected'])->default('proposed');
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['trainer_id', 'level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_level_approvals');
    }
};
