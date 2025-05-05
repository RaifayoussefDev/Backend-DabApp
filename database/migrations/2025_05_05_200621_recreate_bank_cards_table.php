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
        // Recréer la table avec la même structure
        Schema::create('bank_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('card_number')->nullable();
            $table->string('card_holder_name')->nullable();
            $table->string('expiration_date', 5); // Format MM/YY
            $table->string('cvv')->nullable();
            $table->foreignId('card_type_id')->constrained('card_types');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Si vous avez besoin de réimporter des données
        if (file_exists(storage_path('backups/bank_cards_data.sql'))) {
            DB::unprepared(file_get_contents(storage_path('backups/bank_cards_data.sql')));
        }
    }

    public function down()
    {
        Schema::dropIfExists('bank_cards');
    }
};
