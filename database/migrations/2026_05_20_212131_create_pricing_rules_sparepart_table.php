<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePricingRulesSparepartTable extends Migration
{
    public function up()
    {
        Schema::create('pricing_rules_sparepart', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_part_category_id')->constrained('bike_part_categories')->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pricing_rules_sparepart');
    }
}

