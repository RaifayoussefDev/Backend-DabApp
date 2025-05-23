<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('currency_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->onDelete('cascade');
            $table->string('currency_code');    // ex: MAD, USD, EUR
            $table->string('currency_symbol');  // ex: DH, $, €
            $table->decimal('exchange_rate', 10, 4); // ex: 10.00 for MAD, 0.90 for USD
            $table->timestamps();
        });
        DB::table('currency_exchange_rates')->insert([
            [
                'country_id' => 1, 
                'currency_code' => 'SAR',
                'currency_symbol' => 'SAR',
                'exchange_rate' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_id' => 2, // Emarat
                'currency_code' => 'AED',
                'currency_symbol' => 'AED',
                'exchange_rate' => 0.98,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_id' => 3, // Kuwait
                'currency_code' => 'KWD',
                'currency_symbol' => 'KWD',
                'exchange_rate' => 0.082,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_exchange_rates');
    }
};
