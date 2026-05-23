<?php

namespace App\Http\Controllers;

use App\Events\DonationConfirmed;
use App\Models\Donation;
use Spatie\Activitylog\Models\Activity;
use App\Http\Requests\StoreDonationRequest;
use App\Http\Requests\UpdateDonationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use Stripe\Stripe;
use Stripe\Checkout\Session;

class DonationController extends Controller
{
    /**
     * Display a listing of donations (with search & relationships)
     */
    public function index(Request $request)
    {
        $query = Donation::with(['donor', 'project', 'student', 'campaign']);

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($studentId = $request->query('student_id')) {
            $query->where('student_id', $studentId);
        }

        if ($campaignId = $request->query('campaign_id')) {
            $query->where('campaign_id', $campaignId);
        }

        if ($request->filled('is_recurring')) {
            $query->where('is_recurring', filter_var($request->query('is_recurring'), FILTER_VALIDATE_BOOLEAN));
        }

        // global search across receipt_number, donor name, and donor ID code
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('donor', function ($dq) use ($search) {
                        $dq->where('name', 'like', "%{$search}%")
                            ->orWhere('donor_id_code', 'like', "%{$search}%");
                    });
            });
        }

        $donations = $query->latest()->paginate($request->query('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $donations
        ], 200);
    }
    
public function processStripePayment(Request $request)
{
   
    $data = $request->all();
    $data['receipt_number'] = $this->nextReceiptNumber();
    $data['status'] = 'pending';
    
   
    if (!isset($data['payment_method'])) {
        $data['payment_method'] = 'Stripe';
    }

    try {
        $donation = Donation::create($data);
        \Log::info('Donation record created with ID: ' . $donation->id);
    } catch (\Exception $e) {
        \Log::error('Donation Creation Error: ' . $e->getMessage());
        return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
    }

    // 3 stripe session create
    Stripe::setApiKey(env('STRIPE_SECRET'));

    try {
        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => ['name' => 'Donation - ' . $donation->receipt_number],
                    'unit_amount' => (int)($request->amount * 100),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'metadata' => ['donation_id' => $donation->id],
            'success_url' => route('donation.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('donation.cancel'),
        ]);

        // session id update
        $donation->update(['stripe_session_id' => $session->id]);

        return response()->json(['url' => $session->url]);

    } catch (\Exception $e) {
        \Log::error('Stripe Session Error: ' . $e->getMessage());
        return response()->json(['error' => 'Stripe error: ' . $e->getMessage()], 500);
    }
}

     public function success(Request $request)
{
    Stripe::setApiKey(env('STRIPE_SECRET'));
    $session = Session::retrieve($request->get('session_id'));

    if ($session->payment_status === 'paid') {
        $donationId = $session->metadata->donation_id;
        $donation = Donation::find($donationId);

        if ($donation && $donation->status !== 'confirmed') {
            $donation->update(['status' => 'confirmed']);
            DonationConfirmed::dispatch($donation);
        }
    }
    
    return redirect('/donations?status=success'); 
}

    public function cancel()
    {
        return response()->json(['success' => false, 'message' => 'Payment cancelled']);
    }
    /**
     * Store a newly created donation
     */
    public function store(StoreDonationRequest $request)
    {
        $data = $request->validated();

        $data['receipt_number'] = $this->nextReceiptNumber();
        $data['status'] = $data['payment_method'] === 'Stripe' ? 'pending' : ($data['status'] ?? 'confirmed');

        if ($request->boolean('gift_aid')) {
            $data['gift_aid_at'] = now();
        }
        $data['consent_given'] = $request->boolean('consent_given');
        $data['consent_at']    = $request->boolean('consent_given') ? now() : null;

        $data['is_recurring'] = $request->boolean('is_recurring');
        if (!$data['is_recurring']) {
            $data['recurrence_frequency'] = null;
            $data['recurrence_next_at']   = null;
            $data['recurrence_ends_at']   = null;
        }

        $donation = Donation::create($data);

        if ($donation->status === 'confirmed') {
            DonationConfirmed::dispatch($donation);
        }

        return response()->json([
        'success' => true,
        'message' => $donation->status === 'pending' 
            ? 'Donation created! Please complete payment via Stripe.' 
            : 'Donation processed successfully!',
        'data'    => $donation->load(['donor', 'project', 'student', 'campaign']),
    ], 201);
    }

    /**
     * Sequential, unique receipt number in the format REC-100001, REC-100002, ...
     * Reads the highest existing numeric suffix so IDs are not reused after deletes.
     */
    protected function nextReceiptNumber(): string
    {
        $last = Donation::orderByDesc('id')->value('receipt_number');
        $lastNumber = 100000;

        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $lastNumber = max($lastNumber, (int) $m[1]);
        }

        return 'REC-' . ($lastNumber + 1);
    }


    public function show(Donation $donation)
    {
        // No need for findOrFail($id); it is already resolved!
        $donation->load(['donor', 'project', 'student', 'campaign']);

        return response()->json(['success' => true, 'data' => $donation], 200);
    }

  

    public function update(UpdateDonationRequest $request, Donation $donation)
    {
        $previousStatus = $donation->status;
        $donation->update($request->validated());

        if ($previousStatus !== 'confirmed' && $donation->status === 'confirmed') {
            DonationConfirmed::dispatch($donation);
        }

        return response()->json([
            'success' => true,
            'message' => 'Donation tracking record updated!',
            'data'    => $donation->load(['donor', 'project', 'student', 'campaign'])
        ], 200);
    }


    public function destroy(Donation $donation)
    {
        // No need for findOrFail($id); the trait handled the decryption/finding
        $donation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Donation record deleted successfully!'
        ], 200);
    }

    public function getAuditLogs()
    {
        // return last 50 log
        return Activity::latest()->take(50)->get();
    }

public function handleWebhook(Request $request)
{
    $payload = @file_get_contents('php://input');
    $sig_header = $request->header('Stripe-Signature');
    $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Invalid payload'], 400);
    }

    // payment success
    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;
        $donation = \App\Models\Donation::where('stripe_session_id', $session->id)->first();
        
        if ($donation && $donation->status !== 'confirmed') {
            $donation->update(['status' => 'confirmed']);
            
            // event fire
            event(new DonationConfirmed($donation));
        }
    }

    // fail payment
    if ($event->type === 'checkout.session.expired' || $event->type === 'payment_intent.payment_failed') {
        $session = $event->data->object;
        $donation = \App\Models\Donation::where('stripe_session_id', $session->id)->first();
        
        if ($donation && $donation->status !== 'failed') {
            $donation->update(['status' => 'failed']);
            
            // fail  event fire
            event(new DonationFailed($donation));
        }
    }

    return response()->json(['status' => 'success']);
}
}