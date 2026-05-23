<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $to, $content, $parameters, $type;

    public function __construct($to, $content, $parameters = [], $type = 'template')
    {
        $this->to = $to;
        $this->content = $content;
        $this->parameters = $parameters;
        $this->type = $type;
    }

    public function handle(): void
    {
        $phoneId = env('WHATSAPP_META_PHONE_NUMBER_ID');
        $token = env('WHATSAPP_META_TOKEN');
        $url = "https://graph.facebook.com/v21.0/{$phoneId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => preg_replace('/[^0-9]/', '', $this->to),
            'type' => $this->type,
        ];

        if ($this->type === 'template') {
            $payload['template'] = [
                'name' => $this->content,
                'language' => ['code' => 'en'],
                'components' => [['type' => 'body', 'parameters' => $this->parameters]]
            ];
        } else {
            $payload['text'] = ['body' => $this->content];
        }

        $response = Http::withToken($token)->post($url, $payload);

        if ($response->failed()) {
            Log::error("WhatsApp Job Failed: " . $response->body());
        }
    }
}