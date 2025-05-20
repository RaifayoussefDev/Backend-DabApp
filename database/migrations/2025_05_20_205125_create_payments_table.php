<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
        
            // user_id : obligatoire
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
        
            // listing_id : facultatif donc nullable
            $table->foreignId('listing_id')->nullable()->constrained('listings')->onDelete('cascade');
        
            $table->decimal('amount', 10, 2);
        
            // payment_method_id : nullable car onDelete set null
            $table->foreignId('payment_method_id')->nullable()->constrained('card_types')->onDelete('set null');
        
            // bank_card_id : nullable car onDelete set null
            $table->foreignId('bank_card_id')->nullable()->constrained('bank_cards')->onDelete('set null');
        
            $table->enum('payment_status', ['pending', 'completed', 'failed'])->default('pending');
        
            $table->timestamp('created_at')->useCurrent();
        });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
