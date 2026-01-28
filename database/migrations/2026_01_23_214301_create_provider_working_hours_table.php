<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('provider_working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->tinyInteger('day_of_week')->comment('0=Sunday, 6=Saturday');
            $table->boolean('is_open')->default(true);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'day_of_week']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('provider_working_hours');
    }
};