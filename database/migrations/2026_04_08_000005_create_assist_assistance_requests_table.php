<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('seeker_id')->constrained('users');
            $table->foreignId('helper_id')->nullable()->constrained('users');
            $table->uuid('motorcycle_id')->nullable();
            $table->uuid('expertise_type_id');
            $table->enum('status', ['pending', 'accepted', 'en_route', 'arrived', 'completed', 'cancelled'])
                  ->default('pending');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('location_label');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('motorcycle_id')
                ->references('id')->on('assist_motorcycles')
                ->nullOnDelete();

            $table->foreign('expertise_type_id')
                ->references('id')->on('expertise_types');

            $table->index(['status', 'seeker_id', 'helper_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_requests');
    }
};
