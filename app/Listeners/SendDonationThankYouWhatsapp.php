<?php

namespace App\Listeners;

use App\Events\DonationConfirmed;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\WhatsappTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendDonationThankYouWhatsapp implements ShouldQueue
{
    public function handle(DonationConfirmed $event): void
{
    $donation = $event->donation->loadMissing('donor', 'project');
    $donor    = $donation->donor;

    if (! $donor || ! $donor->phone_number) return;

    $template = WhatsappTemplate::where('is_default', true)->first();

  

    if ($template) {
      
        $parameters = [
            ['type' => 'text', 'text' => $donor->name],
            ['type' => 'text', 'text' => (string)$donation->amount],
            ['type' => 'text', 'text' => $donation->project->name ?? 'General'],
            ['type' => 'text', 'text' => now()->format('Y-m-d')],
        ];

        SendWhatsappMessageJob::dispatch($donor->phone_number, $template->name, $parameters, 'template');
    } else {
     
        $body = "Hello {$donor->name}, thank you for your donation of {$donation->amount} BDT.";
        
        SendWhatsappMessageJob::dispatch($donor->phone_number, $body, [], 'text');
    }
}
}
