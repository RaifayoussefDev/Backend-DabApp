<?php

namespace App\Console\Commands;

use App\Models\PaymentSplit;
use App\Models\TrainerBooking;
use App\Models\TrainerPayout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateTrainerPayment extends Command
{
    protected $signature   = 'trainer:simulate-payment {booking_id : The booking ID to mark as paid}';
    protected $description = 'Simulate a PayTabs payment confirmation for a trainer booking (dev/testing only)';

    public function handle(): int
    {
        $bookingId = $this->argument('booking_id');
        $booking   = TrainerBooking::with(['trainer', 'payment'])->find($bookingId);

        if (!$booking) {
            $this->error("Booking #{$bookingId} not found.");
            return 1;
        }

        if ($booking->payment_status === 'paid') {
            $this->warn("Booking #{$bookingId} is already paid.");
            return 0;
        }

        $this->info("Simulating payment confirmation for booking #{$bookingId}...");
        $this->line("  Trainer : {$booking->trainer->name}");
        $this->line("  Amount  : {$booking->price} {$booking->payment->currency}");
        $this->line("  Date    : {$booking->booking_date}");

        DB::beginTransaction();
        try {
            // 1. Mark payment as paid
            $booking->payment->update([
                'tran_ref'       => 'SIM-' . strtoupper(uniqid()),
                'cart_id'        => 'TRAINER_' . $bookingId,
                'resp_code'      => '000',
                'resp_message'   => 'Simulated approval',
                'payment_status' => 'paid',
            ]);

            // 2. Confirm booking
            $booking->update([
                'status'         => 'confirmed',
                'payment_status' => 'paid',
                'confirmed_at'   => now(),
            ]);

            // 3. Commission split
            $commissionPct = $booking->trainer->getEffectiveCommissionPercentage();
            $split         = PaymentSplit::calculate($booking->price, $commissionPct);

            $paymentSplit = PaymentSplit::create([
                'payment_id'            => $booking->payment_id,
                'booking_id'            => $booking->id,
                'trainer_id'            => $booking->trainer_id,
                'total_amount'          => $booking->price,
                'commission_percentage' => $commissionPct,
                'commission_amount'     => $split['commission_amount'],
                'trainer_amount'        => $split['trainer_amount'],
                'currency'              => $booking->payment->currency ?? 'SAR',
                'status'                => 'pending',
            ]);

            // 4. Create payout
            $payout = TrainerPayout::create([
                'trainer_id'       => $booking->trainer_id,
                'payment_split_id' => $paymentSplit->id,
                'amount'           => $split['trainer_amount'],
                'currency'         => $booking->payment->currency ?? 'SAR',
                'status'           => 'pending',
            ]);

            // 5. Increment trainer sessions
            $booking->trainer->incrementTotalSessions();

            DB::commit();

            $this->info("✓ Payment confirmed successfully!");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Booking status',      'confirmed'],
                    ['Payment status',      'paid'],
                    ['Total amount',        "{$booking->price} {$paymentSplit->currency}"],
                    ['Commission (%)',      "{$commissionPct}%"],
                    ['Commission amount',   "{$paymentSplit->commission_amount}"],
                    ['Trainer payout',      "{$payout->amount} (payout #{$payout->id})"],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed: " . $e->getMessage());
            return 1;
        }
    }
}
