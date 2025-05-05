<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // In the new migration file
    public function up()
    {
        // Temporary column to store cleaned dates
        Schema::table('bank_cards', function (Blueprint $table) {
            $table->string('temp_exp_date', 5)->nullable()->after('expiration_date');
        });

        // Clean and move data
        DB::table('bank_cards')->whereNotNull('expiration_date')->each(function ($card) {
            $date = $card->expiration_date;

            // Remove all non-digit characters
            $digits = preg_replace('/[^0-9]/', '', $date);

            // Extract MM and YY (last 2 digits)
            if (strlen($digits) >= 4) {
                $month = substr($digits, 0, 2);
                $year = substr($digits, -2);
                $newDate = $month . '/' . $year;
            } else {
                $newDate = null;
            }

            DB::table('bank_cards')
                ->where('id', $card->id)
                ->update(['temp_exp_date' => $newDate]);
        });

        // Remove old column and rename new one
        Schema::table('bank_cards', function (Blueprint $table) {
            $table->dropColumn('expiration_date');
            $table->renameColumn('temp_exp_date', 'expiration_date');
        });
    }

    public function down()
    {
        // For rollback, you would need to implement the reverse logic
        // This is more complex and may require similar steps
    }
};
