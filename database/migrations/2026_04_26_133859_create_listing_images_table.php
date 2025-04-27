<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('listing_images', function (Blueprint $table) {
            $table->id(); // Auto-incrementing ID
            $table->string('image_url'); // The URL of the image
            $table->foreignId('listing_id') // Foreign key reference to the listing table
                  ->constrained('listings') // Set the foreign key constraint (listing_id references listings table)
                  ->onDelete('cascade'); // Ensure images are deleted if the listing is deleted
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_images');
    }
};
