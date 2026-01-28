<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('riding_instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->string('instructor_name');
            $table->string('instructor_name_ar');
            $table->text('bio')->nullable();
            $table->text('bio_ar')->nullable();
            $table->string('photo')->nullable();
            $table->json('certifications')->nullable()->comment('JSON array of certifications');
            $table->integer('experience_years')->nullable();
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->integer('total_sessions')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['provider_id', 'is_available']);
            $table->index('rating_average');
        });
    }

    public function down()
    {
        Schema::dropIfExists('riding_instructors');
    }
};