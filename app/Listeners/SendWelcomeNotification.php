<?php

namespace App\Listeners;

use App\Events\DonorRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Exception;

class SendWelcomeNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(DonorRegistered $event): void
    {
        $donor = $event->donor;

        try {
           
            $logId = DB::table('email_logs')->insertGetId([
                'recipient_email' => $donor->email,
                'subject' => 'Welcome to Our Donor Community!',
                'status' => 'pending',
                'sent_by' => 'System', 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Mail fire queue 
            Mail::raw("Hello {$donor->name},\n\nThank you for registering as a donor. Your contribution can save lives!", function ($message) use ($donor) {
                $message->to($donor->email)
                        ->subject('Welcome to Our Donor Community!');
            });

            // status update if success
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