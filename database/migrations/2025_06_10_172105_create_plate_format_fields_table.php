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
        Schema::create('plate_format_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plate_format_id')->constrained()->onDelete('cascade');
            $table->string('field_name');
            $table->string('position')->default('center');
            $table->string('character_type'); // 'digit', 'letter'
            $table->string('writing_system'); // 'arabic', 'latin'
            $table->integer('min_length')->default(1);
            $table->integer('max_length')->default(1);
            $table->boolean('is_required')->default(true);
            $table->string('validation_pattern')->nullable();
            $table->integer('font_size')->default(14);
            $table->boolean('is_bold')->default(false);
            $table->integer('display_order')->default(1);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plate_format_fields');
    }
};
