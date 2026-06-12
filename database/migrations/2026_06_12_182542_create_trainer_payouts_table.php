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
        Schema::create('trainer_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->onDelete('cascade');
            $table->foreignId('payment_split_id')->constrained('payment_splits')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');
            $table->enum('status', ['pending', 'approved', 'paid', 'failed'])->default('pending');
            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();
            $table->string('transfer_ref')->nullable();   // bank transfer reference
            $table->string('transfer_proof')->nullable(); // uploaded screenshot/receipt path
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['trainer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_payouts');
    }
};
