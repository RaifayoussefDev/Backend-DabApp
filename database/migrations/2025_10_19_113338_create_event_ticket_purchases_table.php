<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
    {
        Schema::create('event_ticket_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('event_tickets')->onDelete('cascade');
            $table->foreignId('participant_id')->constrained('event_participants')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 10, 2);
            $table->timestamp('purchase_date');
            $table->string('qr_code')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index('ticket_id');
            $table->index('participant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_ticket_purchases');
    }
};
