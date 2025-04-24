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
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('card_number')->nullable(); // Crypté
        $table->string('card_holder_name')->nullable();
        $table->date('expiration_date')->nullable(); // format: YYYY-MM-DD
        $table->string('cvv')->nullable(); // Chiffré
        $table->unsignedBigInteger('card_type_id');
        $table->boolean('is_default')->default(false);
        $table->timestamps();

        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('card_type_id')->references('id')->on('card_types')->onDelete('restrict');
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
