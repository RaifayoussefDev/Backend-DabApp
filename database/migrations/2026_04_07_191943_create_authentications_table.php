<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('authentication', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('token')->change(); // Utilisez 'text' pour une plus grande capacité
            $table->timestamp('token_expiration')->nullable();
            $table->string('refresh_token')->nullable();
            $table->timestamp('refresh_token_expiration')->nullable();
            $table->boolean('is_online')->default(1); // Utilisateur connecté
            $table->timestamp('connection_date')->nullable(); // Date de la connexion
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authentications');
    }
};
