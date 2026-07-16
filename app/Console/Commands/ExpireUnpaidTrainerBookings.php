<?php

namespace App\Console\Commands;

use App\Models\TrainerBooking;
use App\Models\TrainerCourseBooking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireUnpaidTrainerBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trainer-bookings:expire-unpaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel trainer session/course bookings still unpaid 2 hours after creation';

    /**
     * Grace period given to a booking to complete/retry payment before it is
     * auto-cancelled. Matches the 2-hour window requested for the mobile app.
     */
    private const GRACE_PERIOD_HOURS = 2;

    public function handle(): int
    {
        $cutoff = now()->subHours(self::GRACE_PERIOD_HOURS);

        $bookings = TrainerBooking::where('payment_status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($bookings as $booking) {
            $booking->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }

        $this->info("Expired {$bookings->count()} unpaid session booking(s).");

        $courseBookings = TrainerCourseBooking::where('payment_status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($courseBookings as $courseBooking) {
            $courseBooking->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);
            $courseBooking->sessions()->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        $this->info("Expired {$courseBookings->count()} unpaid course booking(s).");

        Log::info('trainer-bookings:expire-unpaid finished', [
            'sessions_cancelled' => $bookings->count(),
            'courses_cancelled'  => $courseBookings->count(),
        ]);

        return Command::SUCCESS;
    }
}
