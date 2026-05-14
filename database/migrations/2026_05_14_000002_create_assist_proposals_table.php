<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assist_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('assistance_requests')->cascadeOnDelete();
            $table->foreignId('helper_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('proposed_price');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->enum('rejection_type', ['manual', 'auto'])->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(['request_id', 'helper_id'], 'unique_proposal_per_request');
            $table->index(['request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assist_proposals');
    }
};
