<?php

namespace App\Http\Controllers;

use App\Jobs\SendBulkEmailJob;
use App\Jobs\SendSmsJob;
use App\Jobs\SendWhatsappMessageJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationRetryController extends Controller
{
    public function retrySms(Request $request, int $id): JsonResponse
    {
        $log = DB::table('sms_logs')->where('id', $id)->first();
        if (! $log) {
            return response()->json(['success' => false, 'message' => 'SMS log not found.'], 404);
        }
        if ($log->status !== 'failed') {
            return response()->json(['success' => false, 'message' => 'Only failed sends can be retried.'], 422);
        }

        SendSmsJob::dispatch(
            $log->recipient_number,
            $log->text,
            $log->template_id,
            $log->recipient_name,
            ($request->user()?->name ?? 'System') . ' (retry)',
            (int) $log->id,
        );

        DB::table('sms_logs')->where('id', $id)->update([
            'status'     => 'retried',
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'SMS re-queued for delivery.',
        ], 202);
    }

    public function retryWhatsapp(Request $request, int $id): JsonResponse
    {
        $log = DB::table('whatsapp_logs')->where('id', $id)->first();
        if (! $log) {
            return response()->json(['success' => false, 'message' => 'WhatsApp log not found.'], 404);
        }
        if ($log->status !== 'failed') {
            return response()->json(['success' => false, 'message' => 'Only failed sends can be retried.'], 422);
        }

        SendWhatsappMessageJob::dispatch(
            $log->recipient_number,
            $log->text,
            $log->template_id,
            $log->recipient_name,
            ($request->user()?->name ?? 'System') . ' (retry)',
            (int) $log->id,
        );

        DB::table('whatsapp_logs')->where('id', $id)->update([
            'status'     => 'retried',
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp message re-queued for delivery.',
        ], 202);
    }

    public function retryEmail(Request $request, int $id): JsonResponse
    {
        $log = DB::table('email_logs')->where('id', $id)->first();
        if (! $log) {
            return response()->json(['success' => false, 'message' => 'Email log not found.'], 404);
        }
        if ($log->status !== 'failed') {
            return response()->json(['success' => false, 'message' => 'Only failed sends can be retried.'], 422);
        }
        if (! $log->email_id) {
            return response()->json(['success' => false, 'message' => 'Original email record missing — cannot retry.'], 422);
        }

        SendBulkEmailJob::dispatch(
            (int) $log->email_id,
            $log->recipient_email,
            (int) $log->id,
        );

        DB::table('email_logs')->where('id', $id)->update([
            'status'     => 'retried',
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email re-queued for delivery.',
        ], 202);
    }
}
