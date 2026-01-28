<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('workshop_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->text('notes');
            $table->text('notes_ar');
            $table->timestamps();

            $table->index('provider_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('workshop_notes');
    }
};