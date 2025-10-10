<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_tag_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained('guides')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('guide_tags')->onDelete('cascade');

            $table->unique(['guide_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_tag_relations');
    }
};
