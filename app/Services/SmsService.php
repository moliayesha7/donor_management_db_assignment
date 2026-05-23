<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsService
{
    public function send(string $to, string $body): array
    {
        $driver = config('services.sms.driver', 'log');

        return match ($driver) {
            'log'    => $this->sendViaLog($to, $body),
            default  => throw new RuntimeException("Unknown SMS driver: {$driver}"),
        };
    }

    private function sendViaLog(string $to, string $body): array
    {
        Log::channel('single')->info('[SMS:LOG]', ['to' => $to, 'body' => $body]);

        return ['status' => 'sent', 'provider_id' => 'log-' . uniqid()];
    }
}
