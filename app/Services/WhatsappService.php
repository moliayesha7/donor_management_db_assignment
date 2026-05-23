<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsappService
{
    public function send(string $to, string $body): array
    {
        $driver = config('services.whatsapp.driver', 'log');

        return match ($driver) {
            'log'    => $this->sendViaLog($to, $body),
            'meta'   => $this->sendViaMeta($to, $body),
            default  => throw new RuntimeException("Unknown WhatsApp driver: {$driver}"),
        };
    }

    private function sendViaMeta(string $to, string $body): array
    {
        $cleanTo = ltrim($to, '+');

        $response = $this->postToMeta([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $cleanTo,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $body,
            ],
        ]);

        if ($response->successful()) {
            return [
                'status'      => 'sent',
                'provider_id' => $response->json('messages.0.id') ?? 'meta-' . uniqid(),
                'context'     => 'meta',
            ];
        }

        $errCode = $response->json('error.code');
        $errMsg  = $response->json('error.message') ?? $response->body();

        Log::error('[WhatsApp:META_ERROR]', [
            'status'  => $response->status(),
            'code'    => $errCode,
            'message' => $errMsg,
            'to'      => $cleanTo,
        ]);

        if ($errCode === 131047 || str_contains((string) $errMsg, 're-engagement')) {
            throw new RuntimeException(
                "Meta 24-hour conversation window expired for {$to}. " .
                "Recipient must reply to your business number first, or use sendMetaTemplate() for re-engagement."
            );
        }

        throw new RuntimeException("Meta WhatsApp send failed ({$response->status()}): {$errMsg}");
    }

    /**
     * Send a pre-approved Meta template (e.g. "hello_world"). Works outside the
     * 24-hour window. Use this for first-contact / re-engagement, then the
     * recipient's reply opens a 24-hour window for free-form text via send().
     */
    public function sendMetaTemplate(string $to, string $templateName, string $languageCode = 'en_US', array $components = []): array
    {
        $cleanTo = ltrim($to, '+');

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $cleanTo,
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];

        if (! empty($components)) {
            $payload['template']['components'] = $components;
        }

        $response = $this->postToMeta($payload);

        if ($response->successful()) {
            return [
                'status'      => 'sent',
                'provider_id' => $response->json('messages.0.id') ?? 'meta-tpl-' . uniqid(),
                'context'     => 'meta-template',
                'template'    => $templateName,
            ];
        }

        $errMsg = $response->json('error.message') ?? $response->body();
        Log::error('[WhatsApp:META_TEMPLATE_ERROR]', ['template' => $templateName, 'error' => $errMsg]);
        throw new RuntimeException("Meta template send failed: {$errMsg}");
    }

    private function postToMeta(array $payload): Response
    {
        $token      = config('services.whatsapp.meta.token');
        $phoneId    = config('services.whatsapp.meta.phone_number_id');
        $apiVersion = config('services.whatsapp.meta.api_version', 'v21.0');

        if (! $token || ! $phoneId) {
            throw new RuntimeException('Meta WhatsApp credentials missing. Check your .env file.');
        }

        return Http::withToken($token)
            ->contentType('application/json')
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneId}/messages", $payload);
    }

    private function sendViaLog(string $to, string $body): array
    {
        Log::channel('single')->info('[WhatsApp:LOG]', ['to' => $to, 'body' => $body]);

        return ['status' => 'sent', 'provider_id' => 'log-' . uniqid()];
    }

}
