<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddPhoneWhatsappToContactingChannelEnumInListingsTable extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE `listings` MODIFY `contacting_channel` ENUM('phone', 'whatsapp', 'phone,whatsapp') NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE `listings` MODIFY `contacting_channel` ENUM('phone', 'whatsapp') NULL");
    }
}
