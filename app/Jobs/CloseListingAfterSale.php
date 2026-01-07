<?php

namespace App\Jobs;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CloseListingAfterSale implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $listingId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($listingId)
    {
        $this->listingId = $listingId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $listing = Listing::find($this->listingId);

            if ($listing && $listing->status !== 'sold') {
                $listing->update([
                    'status' => 'sold',
                    'closed_at' => now(),
                    'closing_reason' => 'sold_via_soom'
                ]);
                Log::info("Listing {$this->listingId} closed automatically 5 days after sale validation.");
            } else {
                Log::info("Listing {$this->listingId} not closed: either not found or already sold.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to close listing {$this->listingId} in delayed job: " . $e->getMessage());
        }
    }
}
