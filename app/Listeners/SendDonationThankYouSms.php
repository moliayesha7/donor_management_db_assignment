<?php

namespace App\Listeners;

use App\Events\DonationConfirmed;
use App\Jobs\SendSmsJob;
use App\Models\SmsTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendDonationThankYouSms implements ShouldQueue
{
    public function handle(DonationConfirmed $event): void
    {
        $donation = $event->donation->loadMissing('donor', 'project');
        $donor    = $donation->donor;

        if (! $donor || ! $donor->phone_number) {
            Log::info("DonationConfirmed: skipping SMS — donor missing or no phone number (donation #{$donation->id})");
            return;
        }

        if (! $donor->notify_sms) {
            Log::info("DonationConfirmed: skipping SMS — donor opted out (donation #{$donation->id})");
            return;
        }

        $template = SmsTemplate::where('is_default', true)->first();

        $body = $template
            ? $template->sms_body
            : "Hello [donor-name], thank you for your donation of {amount}. Receipt: {receipt}.";

        $body = strtr($body, [
            '[donor-name]' => $donor->name,
            '{amount}'     => number_format((float) $donation->amount, 2),
            '{receipt}'    => $donation->receipt_number,
            '{project}'    => $donation->project->name ?? '',
        ]);

        SendSmsJob::dispatch(
            $donor->phone_number,
            $body,
            $template?->id,
            $donor->name,
            'Auto-trigger (donation confirmed)',
        );
    }
}
