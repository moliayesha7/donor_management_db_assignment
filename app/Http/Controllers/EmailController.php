<?php

namespace App\Http\Controllers;

use App\Jobs\SendBulkEmailJob;
use App\Models\Email;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/emails",
     * summary="List all drafted/sent emails",
     * tags={"Emails"},
     * @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $query = Email::query()->with('creator:id,name');

        // সার্চ ফিল্টার (Subject বা Recipients দিয়ে সার্চ করার জন্য)
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('recipients', 'like', "%{$search}%");
            });
        }

        $emails = $query->latest()->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $emails
        ], 200);
    }

    /**
     * @OA\Post(
     * path="/api/emails",
     * summary="Compose and save/send a new email",
     * tags={"Emails"},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"recipients", "subject", "body"},
     * @OA\Property(property="recipients", type="string", example="donor1@mail.com, donor2@mail.com"),
     * @OA\Property(property="template_id", type="integer", example=1),
     * @OA\Property(property="subject", type="string", example="Thank you for your support"),
     * @OA\Property(property="body", type="string", example="Dear [donor-name]..."),
     * @OA\Property(property="selected_projects", type="array", @OA\Items(type="string"), example={"all", "zakat"}),
     * @OA\Property(property="send_timing", type="string", enum={"Now", "Later"}, example="Now"),
     * @OA\Property(property="scheduled_at", type="string", format="date-time", nullable=true)
     * )
     * ),
     * @OA\Response(response=201, description="Email composed successfully")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipients'        => 'required|string',
            'template_id'       => 'nullable|integer',
            'subject'           => 'required|string|max:255',
            'body'              => 'required|string',
            'selected_projects' => 'nullable|array',
            'send_timing'       => 'required|in:Now,Later',
            'scheduled_at'      => 'required_if:send_timing,Later|nullable|date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $email = Email::create([
            'recipients'        => $request->recipients,
            'template_id'       => $request->template_id,
            'subject'           => $request->subject,
            'body'              => $request->body,
            'selected_projects' => $request->selected_projects,
            'send_timing'       => $request->send_timing,
            'scheduled_at'      => $request->scheduled_at,
            'status'            => $request->send_timing === 'Now' ? 'pending' : 'draft', // 'Now' হলে মেইলার কিউতে যাবে
            'created_by'        => auth()->id() ?? 1, // টেস্টিং এর সুবিধার্থে ওয়ান-টাইম ফলব্যাক ১ রাখা হলো
        ]);

        // 💡 মেইল কিউ প্রোসেস করার জন্য এখানে Job ডিসপ্যাচ করা যেতে পারে:
        // if ($email->send_timing === 'Now') { dispatch(new SendBulkEmailJob($email)); }

        $dispatched = 0;
        if ($email->send_timing === 'Now') {
            $recipients = collect(preg_split('/[\s,;]+/', $email->recipients))
                ->map(fn ($r) => trim($r))
                ->filter(fn ($r) => filter_var($r, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values();

            foreach ($recipients as $recipient) {
                SendBulkEmailJob::dispatch($email->id, $recipient);
                $dispatched++;
            }
        }

        return response()->json([
            'success'               => true,
            'message'               => $email->send_timing === 'Now'
                ? "Email queued for {$dispatched} recipient(s)!"
                : 'Email draft saved.',
            'data'                  => $email,
            'recipients_dispatched' => $dispatched,
        ], 201);
    }

    /**
     * @OA\Get(
     * path="/api/emails/{id}",
     * summary="Get details of a composed email",
     * tags={"Emails"},
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show($id)
    {
        $email = Email::with('creator:id,name')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $email
        ], 200);
    }

    /**
     * @OA\Put(
     * path="/api/emails/{id}",
     * summary="Update email log / draft",
     * tags={"Emails"},
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Updated successfully")
     * )
     */
    public function update(Request $request, $id)
    {
        $email = Email::findOrFail($id);

        // যদি অলরেডি মেইল পাঠানো হয়ে যায়, তবে রি-এডিট ব্লক করা ভালো
        if ($email->status === 'sent') {
            return response()->json(['success' => false, 'message' => 'Cannot modify an already sent email.'], 400);
        }

        $validator = Validator::make($request->all(), [
            'recipients'        => 'sometimes|required|string',
            'subject'           => 'sometimes|required|string|max:255',
            'body'              => 'sometimes|required|string',
            'selected_projects' => 'nullable|array',
            'send_timing'       => 'sometimes|required|in:Now,Later',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $email->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Email log updated successfully!',
            'data'    => $email
        ], 200);
    }

    /**
     * @OA\Delete(
     * path="/api/emails/{id}",
     * summary="Delete an email from history/draft",
     * tags={"Emails"},
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Deleted successfully")
     * )
     */
    public function destroy($id)
    {
        $email = Email::findOrFail($id);
        $email->delete();

        return response()->json([
            'success' => true,
            'message' => 'Email entry deleted successfully!'
        ], 200);
    }

    /**
 * @OA\Get(
 * path="/api/email-schedules",
 * summary="Get all email batch schedules/queues",
 * tags={"Emails"}
 * )
 */
public function getSchedules(Request $request)
{
    // স্ক্রিনশটের গ্রিডের মতো ডেটা তুলে আনা
    $schedules = \DB::table('email_schedules')
        ->select('created_at', 'subject', 'deadline', 'status', 'started_at', 'completed_at')
        ->orderBy('created_at', 'desc')
        ->paginate($request->query('per_page', 10)); // ডিফল্ট ১০টি এন্ট্রি

    return response()->json([
        'success' => true,
        'data' => $schedules
    ], 200);
}

/**
 * @OA\Get(
 * path="/api/email-logs",
 * summary="Get individual recipient email history logs",
 * tags={"Emails"}
 * )
 */
public function getLogs(Request $request)
{
    // স্ক্রিনশটের ডেটা গ্রিডের মতো (Date/Time, Subject, Sent By, Recipient Name/Email)
    $query = \DB::table('email_logs')
        ->select(
            'id',
            \DB::raw('DATE(created_at) as date'),
            \DB::raw('TIME_FORMAT(created_at, "%H:%i") as time'),
            'subject',
            'sent_by',
            'recipient_name',
            'recipient_email',
            'status',
            'provider_id',
            'attempts',
            'error_message'
        );

    if ($search = $request->query('search')) {
        $query->where('subject', 'like', "%{$search}%")
              ->orWhere('recipient_email', 'like', "%{$search}%")
              ->orWhere('recipient_name', 'like', "%{$search}%");
    }

    $logs = $query->orderBy('created_at', 'desc')->paginate($request->query('per_page', 10));

    return response()->json([
        'success' => true,
        'data' => $logs
    ], 200);
}
}