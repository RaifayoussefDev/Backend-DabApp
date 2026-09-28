<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mirrors trainer_payouts: one payout consolidates a period of order items.
        Schema::create('marketplace_vendor_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('marketplace_vendors')->restrictOnDelete();
            $table->foreignId('commission_id')->nullable()->constrained('commission_settings')->nullOnDelete(); // rule applied (audit)
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_sales', 12, 2)->default(0);
            $table->decimal('commission', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();
            $table->string('transfer_ref')->nullable();
            $table->string('transfer_proof')->nullable();
            $table->json('items_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'status']);
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_vendor_payouts');
    }
};
