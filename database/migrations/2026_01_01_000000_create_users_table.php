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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->date('birthday')->nullable();
            $table->string('gender')->nullable();
            $table->string('profile_picture')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code')->nullable();
            $table->boolean('verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('token')->nullable();
            $table->timestamp('token_expiration')->nullable();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->string('language')->default('en');
            $table->string('timezone')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->foreignId('country_id')->nullable()->constrained('countries')->onDelete('set null');

        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
