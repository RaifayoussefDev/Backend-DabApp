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
        Schema::create('service_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('subscription_plans')->onDelete('cascade');
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->enum('status', ['active', 'cancelled', 'expired', 'payment_failed', 'trial'])->default('active');
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->date('next_billing_date')->nullable();
            $table->date('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('payment_method_id')->nullable()->constrained('card_types')->onDelete('set null');
            $table->foreignId('bank_card_id')->nullable()->constrained('bank_cards')->onDelete('set null');
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();

            // Indexes avec noms courts
            $table->index(['provider_id', 'status'], 'svc_sub_provider_status_idx');
            $table->index('status', 'svc_sub_status_idx');
            $table->index('next_billing_date', 'svc_sub_next_billing_idx');
            $table->index(['current_period_start', 'current_period_end'], 'svc_sub_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_subscriptions');
    }
};