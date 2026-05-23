<?php

namespace App\Jobs;

use App\Mail\BulkDonorEmail;
use App\Models\Donor;
use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendBulkEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $emailId,
        public string $recipient,
        public ?int $retryOfLogId = null,
    ) {}

    public function handle(): void
    {
        $email = Email::find($this->emailId);
        if (! $email) {
            return;
        }

        $donor = Donor::where('email', $this->recipient)->first();

        $sent = Mail::to($this->recipient)->send(new BulkDonorEmail(
            emailSubject:  $email->subject,
            emailBody:     $email->body,
            recipientName: $donor?->name,
        ));

        $messageId = $sent?->getSymfonySentMessage()?->getMessageId();

        DB::table('email_logs')->insert([
            'email_id'        => $email->id,
            'subject'         => $email->subject,
            'sent_by'         => $email->creator->name ?? 'System',
            'recipient_name'  => $donor?->name,
            'recipient_email' => $this->recipient,
            'status'          => 'sent',
            'provider_id'     => $messageId,
            'attempts'        => $this->attempts(),
            'retry_of_log_id' => $this->retryOfLogId,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->markEmailSentIfAllDelivered($email);
    }

    public function failed(?Throwable $e): void
    {
        Log::error("SendBulkEmailJob failed for {$this->recipient}: " . ($e?->getMessage() ?? 'unknown'));

        $email = Email::find($this->emailId);
        $donor = Donor::where('email', $this->recipient)->first();

        DB::table('email_logs')->insert([
            'email_id'        => $this->emailId,
            'subject'         => $email?->subject ?? '',
            'sent_by'         => $email?->creator?->name ?? 'System',
            'recipient_name'  => $donor?->name,
            'recipient_email' => $this->recipient,
            'status'          => 'failed',
            'attempts'        => $this->attempts(),
            'retry_of_log_id' => $this->retryOfLogId,
            'error_message'   => $e?->getMessage(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        if ($email && $email->status !== 'failed') {
            $email->update(['status' => 'failed']);
        }
    }

    private function markEmailSentIfAllDelivered(Email $email): void
    {
        $totalRecipients = collect(preg_split('/[\s,;]+/', $email->recipients))
            ->filter(fn ($r) => filter_var($r, FILTER_VALIDATE_EMAIL))
            ->count();

        $logged = DB::table('email_logs')
            ->where('email_id', $email->id)
            ->whereIn('status', ['sent', 'failed'])
            ->count();

        if ($logged >= $totalRecipients && $email->status !== 'failed') {
            $email->update(['status' => 'sent']);
        }
    }
}
