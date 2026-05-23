<?php

namespace App\Http\Controllers;

use App\Jobs\SendSmsJob;
use App\Models\Donor;
use App\Models\SmsTemplate;
use App\Http\Requests\StoreSmsTemplateRequest;
use App\Http\Requests\UpdateSmsTemplateRequest;
use Illuminate\Http\Request;

class SmsTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = SmsTemplate::query();
        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        return response()->json(['success' => true, 'data' => $query->latest()->get()], 200);
    }

    public function store(StoreSmsTemplateRequest $request)
    {
        if ($request->is_default) {
            SmsTemplate::where('is_default', true)->update(['is_default' => false]);
        }
        $template = SmsTemplate::create($request->validated());
        return response()->json(['success' => true, 'message' => 'SMS Template created!', 'data' => $template], 201);
    }

    public function show($id)
    {
        return response()->json(['success' => true, 'data' => SmsTemplate::findOrFail($id)], 200);
    }

    public function update(UpdateSmsTemplateRequest $request, $id)
    {
        $template = SmsTemplate::findOrFail($id);
        if ($request->is_default) {
            SmsTemplate::where('id', '!=', $id)->where('is_default', true)->update(['is_default' => false]);
        }
        $template->update($request->validated());
        return response()->json(['success' => true, 'message' => 'SMS Template updated!', 'data' => $template], 200);
    }

    public function destroy($id)
    {
        SmsTemplate::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'SMS Template deleted!'], 200);
    }

    /**
     * Send an SMS to one or more donor IDs or raw numbers using a template body or custom text.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'template_id'  => 'nullable|exists:sms_templates,id',
            'body'         => 'required_without:template_id|string',
            'recipients'   => 'required|array|min:1',
            'recipients.*' => 'string',
        ]);

        $body = $validated['body'] ?? SmsTemplate::findOrFail($validated['template_id'])->sms_body;
        $sentBy = $request->user()?->name ?? 'System';

        $dispatched = 0;
        foreach ($validated['recipients'] as $entry) {
            $donor = is_numeric($entry)
                ? Donor::find((int) $entry)
                : Donor::where('phone_number', $entry)->first();

            $number = $donor?->phone_number ?? (is_numeric($entry) ? null : $entry);
            if (! $number) {
                continue;
            }

            $personalized = $donor?->name
                ? str_replace('[donor-name]', $donor->name, $body)
                : $body;

            SendSmsJob::dispatch(
                $number,
                $personalized,
                $validated['template_id'] ?? null,
                $donor?->name,
                $sentBy,
            );
            $dispatched++;
        }

        return response()->json([
            'success'    => true,
            'message'    => "SMS queued for {$dispatched} recipient(s).",
            'dispatched' => $dispatched,
        ], 201);
    }

    /**
 * Get SMS Batch Schedules
 */
public function getSchedules(Request $request)
{
    $schedules = \DB::table('sms_schedules')
        ->orderBy('created_at', 'desc')
        ->paginate($request->query('per_page', 10));

    return response()->json(['success' => true, 'data' => $schedules], 200);
}

/**
 * Get Individual SMS Logs (As seen in the screenshot)
 */
public function getLogs(Request $request)
{
    $query = \DB::table('sms_logs')
        ->select(
            'id',
            \DB::raw('DATE(created_at) as date'),
            \DB::raw('TIME_FORMAT(created_at, "%H:%i") as time'),
            'text',
            'sent_by',
            'recipient_name',
            'recipient_number',
            'status',
            'provider_id',
            'attempts',
            'error_message'
        );

    // search filter (for searching by message content or phone number)
    if ($search = $request->query('search')) {
        $query->where('text', 'like', "%{$search}%")
              ->orWhere('recipient_number', 'like', "%{$search}%")
              ->orWhere('recipient_name', 'like', "%{$search}%");
    }

    $logs = $query->orderBy('created_at', 'desc')->paginate($request->query('per_page', 10));

    return response()->json(['success' => true, 'data' => $logs], 200);
}
}