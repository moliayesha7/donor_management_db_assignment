<?php

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public string $recipientNumber,
        public string $body,
        public ?int $templateId = null,
        public ?string $recipientName = null,
        public ?string $sentBy = null,
        public ?int $retryOfLogId = null,
    ) {}

    public function handle(SmsService $sms): void
    {
        $result = $sms->send($this->recipientNumber, $this->body);

        DB::table('sms_logs')->insert([
            'template_id'      => $this->templateId,
            'recipient_number' => $this->recipientNumber,
            'recipient_name'   => $this->recipientName,
            'text'             => $this->body,
            'sent_by'          => $this->sentBy,
            'status'           => $result['status'] ?? 'sent',
            'provider_id'      => $result['provider_id'] ?? null,
            'attempts'         => $this->attempts(),
            'retry_of_log_id'  => $this->retryOfLogId,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error("SendSmsJob failed for {$this->recipientNumber}: " . ($e?->getMessage() ?? 'unknown'));

        DB::table('sms_logs')->insert([
            'template_id'      => $this->templateId,
            'recipient_number' => $this->recipientNumber,
            'recipient_name'   => $this->recipientName,
            'text'             => $this->body,
            'sent_by'          => $this->sentBy,
            'status'           => 'failed',
            'attempts'         => $this->attempts(),
            'retry_of_log_id'  => $this->retryOfLogId,
            'error_message'    => $e?->getMessage(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }
}
