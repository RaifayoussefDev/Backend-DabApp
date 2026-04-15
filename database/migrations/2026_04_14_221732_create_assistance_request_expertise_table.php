<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_request_expertise', function (Blueprint $table) {
            $table->unsignedBigInteger('assistance_request_id');
            $table->unsignedBigInteger('expertise_type_id');

            $table->foreign('assistance_request_id')
                ->references('id')->on('assistance_requests')
                ->cascadeOnDelete();

            $table->foreign('expertise_type_id')
                ->references('id')->on('expertise_types')
                ->cascadeOnDelete();

            $table->primary(['assistance_request_id', 'expertise_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_request_expertise');
    }
};
