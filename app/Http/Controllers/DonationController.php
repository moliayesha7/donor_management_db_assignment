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

        // ডোনারের নাম দিয়ে গ্লোবাল লাইভ সার্চ
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

    /**
     * Store a newly created donation
     */
    public function store(StoreDonationRequest $request)
    {
        $data = $request->validated();

        $data['receipt_number'] = $this->nextReceiptNumber();
        $data['status'] = $data['status'] ?? 'confirmed';

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
            'message' => 'Donation processed successfully!',
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

    /**
     * Display the specified donation
     */
    // public function show($id)
    // {
    //     $donation = Donation::with(['donor', 'project', 'student'])->findOrFail($id);
    //     return response()->json(['success' => true, 'data' => $donation], 200);
    // }

    public function show(Donation $donation)
    {
        // No need for findOrFail($id); it is already resolved!
        $donation->load(['donor', 'project', 'student', 'campaign']);

        return response()->json(['success' => true, 'data' => $donation], 200);
    }

    /**
     * Update the specified donation
     */
    // public function update(UpdateDonationRequest $request, Donation $donation)
    // {
    //     $donation = Donation::findOrFail($id);
    //     $previousStatus = $donation->status;
    //     $donation->update($request->validated());

    //     if ($previousStatus !== 'confirmed' && $donation->status === 'confirmed') {
    //         DonationConfirmed::dispatch($donation);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Donation tracking record updated!',
    //         'data' => $donation->load(['donor', 'project', 'student'])
    //     ], 200);
    // }

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

    /**
     * Remove the specified donation
     */
    // public function destroy($id)
    // {
    //     $donation = Donation::findOrFail($id);
    //     $donation->delete();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Donation record deleted successfully!'
    //     ], 200);
    // }

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
        // সর্বশেষ ৫০টি লগ রিটার্ন করবে
        return Activity::latest()->take(50)->get();
    }
}