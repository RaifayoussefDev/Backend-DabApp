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
        Schema::table('payments', function (Blueprint $table) {
            // Champs PayTabs
            $table->string('tran_ref')->nullable()->after('payment_status');
            $table->string('cart_id')->nullable()->after('tran_ref');
            $table->string('resp_code')->nullable()->after('cart_id');
            $table->text('resp_message')->nullable()->after('resp_code');
            $table->json('verification_data')->nullable()->after('resp_message');

            // Ajouter updated_at si pas déjà présent
            if (!Schema::hasColumn('payments', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }

            // Index pour améliorer les performances
            $table->index('tran_ref');
            $table->index('cart_id');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['tran_ref']);
            $table->dropIndex(['cart_id']);
            $table->dropIndex(['payment_status']);

            $table->dropColumn([
                'tran_ref',
                'cart_id',
                'resp_code',
                'resp_message',
                'verification_data'
            ]);

            // Ne supprimez updated_at que si vous l'avez ajouté
            // $table->dropColumn('updated_at');
        });
    }
};
