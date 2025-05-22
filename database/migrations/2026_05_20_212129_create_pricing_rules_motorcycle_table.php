<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePricingRulesMotorcycleTable extends Migration
{
    public function up()
    {
        Schema::create('pricing_rules_motorcycle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorcycle_type_id')->constrained('motorcycle_types')->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pricing_rules_motorcycle');
    }
}

