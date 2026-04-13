<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicite_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banner_id')->constrained('banners')->onDelete('cascade');
            $table->string('nom');
            $table->string('prenom');
            $table->string('phone');
            $table->string('city');
            // Track whether this row has been synced to Google Sheets (for retry on failure)
            $table->boolean('synced_to_sheet')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicite_submissions');
    }
};
