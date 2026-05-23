<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationWebhookController extends Controller
{

    /** Meta WhatsApp Cloud API: GET = verify handshake, POST = status callback. */
    public function meta(Request $request): Response|JsonResponse
    {
        if ($request->isMethod('get')) {
            $expected = config('services.whatsapp.meta.webhook_verify_token');
            if ($request->query('hub_mode') === 'subscribe'
                && $expected
                && hash_equals((string) $expected, (string) $request->query('hub_verify_token'))) {
                return response((string) $request->query('hub_challenge'), 200);
            }
            return response('forbidden', 403);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['statuses'] ?? []) as $s) {
                    if (empty($s['id']) || empty($s['status'])) continue;

                    $update = ['status' => $s['status'], 'updated_at' => now()];
                    if (! empty($s['errors'][0]['message'])) {
                        $update['error_message'] = $s['errors'][0]['message'];
                    }
                    DB::table('whatsapp_logs')->where('provider_id', $s['id'])->update($update);
                }
            }
        }

        return response()->json(['ok' => true]);
    }
}
