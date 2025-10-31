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
        Schema::create('poi_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poi_id')->constrained('points_of_interest')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('reason')->comment('Closed, Wrong Info, Duplicate, etc.');
            $table->text('description')->nullable();
            $table->string('status', 50)->default('pending')->comment('pending, resolved, rejected');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poi_reports');
    }
};
