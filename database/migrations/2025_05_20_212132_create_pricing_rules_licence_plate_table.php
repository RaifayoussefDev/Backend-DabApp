<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePricingRulesLicencePlateTable extends Migration
{
    public function up()
    {
        Schema::create('pricing_rules_licence_plate', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plate_type_id')->constrained('plate_types')->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pricing_rules_licence_plate');
    }
}