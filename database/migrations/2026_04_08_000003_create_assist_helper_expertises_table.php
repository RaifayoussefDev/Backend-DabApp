<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helper_expertises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('helper_profile_id');
            $table->uuid('expertise_type_id');
            $table->timestamps();

            $table->foreign('helper_profile_id')
                ->references('id')->on('helper_profiles')
                ->cascadeOnDelete();

            $table->foreign('expertise_type_id')
                ->references('id')->on('expertise_types')
                ->cascadeOnDelete();

            $table->unique(['helper_profile_id', 'expertise_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helper_expertises');
    }
};
