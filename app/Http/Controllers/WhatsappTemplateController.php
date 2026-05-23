<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWhatsappTemplateRequest;
use App\Http\Requests\UpdateWhatsappTemplateRequest;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\Donor;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsappTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = WhatsappTemplate::query();
        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        return response()->json(['success' => true, 'data' => $query->latest()->get()], 200);
    }

    public function store(StoreWhatsappTemplateRequest $request)
    {
        if ($request->is_default) {
            WhatsappTemplate::where('is_default', true)->update(['is_default' => false]);
        }
        $template = WhatsappTemplate::create($request->validated());
        return response()->json(['success' => true, 'message' => 'WhatsApp template created!', 'data' => $template], 201);
    }

    public function show($id)
    {
        return response()->json(['success' => true, 'data' => WhatsappTemplate::findOrFail($id)], 200);
    }

    public function update(UpdateWhatsappTemplateRequest $request, $id)
    {
        $template = WhatsappTemplate::findOrFail($id);
        if ($request->is_default) {
            WhatsappTemplate::where('id', '!=', $id)->where('is_default', true)->update(['is_default' => false]);
        }
        $template->update($request->validated());
        return response()->json(['success' => true, 'message' => 'WhatsApp template updated!', 'data' => $template], 200);
    }

    public function destroy($id)
    {
        WhatsappTemplate::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'WhatsApp template deleted!'], 200);
    }

    /**
     * Send a WhatsApp message to one or more donor IDs or raw numbers using a template body.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'template_id'   => 'nullable|exists:whatsapp_templates,id',
            'body'          => 'required_without:template_id|string',
            'recipients'    => 'required|array|min:1',
            'recipients.*'  => 'string',
        ]);

        $body = $validated['body'] ?? WhatsappTemplate::findOrFail($validated['template_id'])->body;
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

            SendWhatsappMessageJob::dispatch(
                $number,
                $personalized,
                $validated['template_id'] ?? null,
                $donor?->name,
                $sentBy,
            );
            $dispatched++;
        }

        return response()->json([
            'success'              => true,
            'message'              => "WhatsApp queued for {$dispatched} recipient(s).",
            'dispatched'           => $dispatched,
        ], 201);
    }

    public function getLogs(Request $request)
    {
        $query = DB::table('whatsapp_logs');

        if ($search = $request->query('search')) {
            $query->where('text', 'like', "%{$search}%")
                  ->orWhere('recipient_number', 'like', "%{$search}%")
                  ->orWhere('recipient_name', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data'    => $query->orderByDesc('created_at')->paginate($request->query('per_page', 10)),
        ], 200);
    }
}
