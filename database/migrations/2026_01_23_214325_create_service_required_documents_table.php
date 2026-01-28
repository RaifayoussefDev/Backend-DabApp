<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_required_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->string('document_name');
            $table->string('document_name_ar');
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->boolean('is_required')->default(true);
            $table->integer('order_position')->default(0);
            $table->timestamps();

            $table->index(['service_id', 'order_position']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_required_documents');
    }
};