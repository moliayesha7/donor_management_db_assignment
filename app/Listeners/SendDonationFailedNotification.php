<?php

namespace App\Listeners;

use App\Events\DonationFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Exception;

class SendDonationFailedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(DonationFailed $event): void
    {
        $donation = $event->donation;
        $donor = $donation->donor;

        try {
            $logId = DB::table('email_logs')->insertGetId([
                'recipient_email' => $donor->email,
                'subject' => 'Donation Payment Failed Alert',
                'status' => 'pending',
                'sent_by' => 'System', 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $mailContent = "Hello {$donor->name},\n\n"
                         . "We noticed that your recent donation attempt of {$donation->amount} POUND has failed.\n"
                         . "Reference Number: {$donation->receipt_number}\n\n"
                         . "If this was unexpected, please try again or contact your payment provider.\n\n"
                         . "Thank you for your intent to support us.";

            Mail::raw($mailContent, function ($message) use ($donor) {
                $message->to($donor->email)
                        ->subject('Donation Payment Failed Alert');
            });

            DB::table('email_logs')->where('id', $logId)->update([
                'status' => 'sent',
                'updated_at' => now()
            ]);

        } catch (Exception $e) {
            if (isset($logId)) {
                DB::table('email_logs')->where('id', $logId)->update([
                    'status' => 'failed',
                    'updated_at' => now()
                ]);
            }
            throw $e;
        }
    }
}