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
        Schema::create('bank_cards', function (Blueprint $table) {
            $table->engine = 'InnoDB'; // ✅ Important pour les clés étrangères

            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // ✅ plus propre et sûr
            $table->string('card_number')->nullable(); // Crypté
            $table->string('card_holder_name')->nullable();
            $table->date('expiration_date')->nullable(); // format: YYYY-MM-DD
            $table->string('cvv')->nullable(); // Chiffré
            $table->foreignId('card_type_id')->constrained('card_types')->onDelete('restrict'); // ✅ plus clair
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_cards');
    }
};
