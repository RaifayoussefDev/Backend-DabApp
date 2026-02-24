<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('poi_tags', function (Blueprint $row) {
            $row->id();
            $row->string('name', 100)->unique();
            $row->string('slug', 100)->unique();
            $row->timestamps();
        });

        Schema::create('poi_tag_relations', function (Blueprint $row) {
            $row->id();
            $row->foreignId('poi_id')->constrained('points_of_interest')->onDelete('cascade');
            $row->foreignId('tag_id')->constrained('poi_tags')->onDelete('cascade');
            $row->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poi_tag_relations');
        Schema::dropIfExists('poi_tags');
    }
};
