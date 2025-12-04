<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admin_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('admin_menus')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('icon', 50)->nullable();
            $table->string('route', 255)->nullable();
            $table->string('permission', 100)->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'order']);
            $table->index('permission');
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_menus');
    }
};
