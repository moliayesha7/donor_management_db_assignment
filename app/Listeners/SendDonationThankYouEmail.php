<?php

namespace App\Listeners;

use App\Events\DonationConfirmed;
use App\Mail\DonationThankYou;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDonationThankYouEmail implements ShouldQueue
{
    public function handle(DonationConfirmed $event): void
    {
        $donation = $event->donation->loadMissing('donor', 'project');
        $donor    = $donation->donor;

        if (! $donor || ! $donor->email) {
            Log::info("DonationConfirmed: skipping email — donor missing or no email address (donation #{$donation->id})");
            return;
        }

        if (! $donor->notify_email) {
            Log::info("DonationConfirmed: skipping email — donor opted out (donation #{$donation->id})");
            return;
        }

        Mail::to($donor->email)->send(new DonationThankYou($donation));
    }
}
