<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assist_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('request_id')->nullable();
            $table->string('type'); // new_request, accepted, en_route, arrived, completed, rated
            $table->string('title');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->foreign('request_id')
                ->references('id')->on('assistance_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assist_notifications');
    }
};
