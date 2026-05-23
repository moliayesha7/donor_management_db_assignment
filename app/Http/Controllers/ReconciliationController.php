<?php

namespace App\Http\Controllers;

use App\Models\BankStatementUpload;
use App\Models\BankTransaction;
use App\Models\Donor;
use App\Services\ReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReconciliationController extends Controller
{
    public function __construct(protected ReconciliationService $service) {}

    /**
     * @OA\Get(
     *     path="/api/reconciliation/template",
     *     summary="Download a blank Excel template for bank/external donation imports",
     *     tags={"Reconciliation"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Template_external_donation_data.xlsx")
     * )
     */
    public function template()
    {
        $contents = $this->service->buildTemplateXlsx();

        return response($contents, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Template_external_donation_data.xlsx"',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/reconciliation/uploads",
     *     summary="List bank statement uploads with stats",
     *     tags={"Reconciliation"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index()
    {
        $uploads = BankStatementUpload::with(['uploader:id,name', 'defaultProject:id,name,project_code'])
            ->latest()
            ->get();

        $totals = [
            'uploads'        => $uploads->count(),
            'total_rows'     => (int) $uploads->sum('total_rows'),
            'matched_rows'   => (int) $uploads->sum('matched_rows'),
            'unmatched_rows' => (int) $uploads->sum('unmatched_rows'),
            'amount_total'   => (float) $uploads->sum('total_amount'),
        ];

        return response()->json([
            'success' => true,
            'data'    => ['uploads' => $uploads, 'totals' => $totals],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/reconciliation/uploads",
     *     summary="Upload a bank statement / external donation file (xlsx or csv) and run matching",
     *     tags={"Reconciliation"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(property="file", type="string", format="binary"),
     *                 @OA\Property(property="default_project_id", type="integer"),
     *                 @OA\Property(property="auto_create_donors", type="boolean")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Upload processed"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'file'                => 'required|file|mimes:xlsx,csv,txt|max:20480',
            'default_project_id'  => 'nullable|exists:projects,id',
            'auto_create_donors'  => 'nullable|boolean',
        ]);

        $uploadedFile = $request->file('file');
        $extension    = strtolower($uploadedFile->getClientOriginalExtension());
        $format       = $extension === 'csv' || $extension === 'txt' ? 'csv' : 'xlsx';
        $storedPath   = $uploadedFile->store('reconciliation-uploads');

        $upload = BankStatementUpload::create([
            'original_name'      => $uploadedFile->getClientOriginalName(),
            'stored_path'        => $storedPath,
            'format'             => $format,
            'default_project_id' => $request->default_project_id,
            'uploaded_by'        => $request->user()->id,
        ]);

        $upload = $this->service->processUpload(
            $upload,
            (bool) $request->boolean('auto_create_donors', false)
        );

        return response()->json([
            'success' => $upload->status === 'completed',
            'message' => $upload->status === 'completed'
                ? "Processed {$upload->total_rows} rows: {$upload->matched_rows} matched, {$upload->unmatched_rows} unmatched."
                : ('Upload failed: ' . ($upload->notes ?? 'Unknown error')),
            'data'    => $upload->load(['uploader:id,name', 'defaultProject:id,name,project_code']),
        ], $upload->status === 'completed' ? 201 : 422);
    }

    /**
     * @OA\Get(
     *     path="/api/reconciliation/uploads/{id}",
     *     summary="Get one upload's transactions (optionally filter by match_status)",
     *     tags={"Reconciliation"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="match_status", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show(Request $request, $id)
    {
        $upload = BankStatementUpload::with(['uploader:id,name', 'defaultProject:id,name,project_code'])
            ->findOrFail($id);

        $txnQuery = BankTransaction::where('upload_id', $upload->id)
            ->with(['matchedDonor:id,donor_id_code,name,email,phone_number', 'createdDonation:id,receipt_number,amount']);

        if ($status = $request->query('match_status')) {
            $txnQuery->where('match_status', $status);
        }

        $transactions = $txnQuery->orderBy('row_number')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'upload'       => $upload,
                'transactions' => $transactions,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/reconciliation/unmatched",
     *     summary="List all unmatched transactions across uploads",
     *     tags={"Reconciliation"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function unmatched()
    {
        $rows = BankTransaction::with(['upload:id,original_name'])
            ->whereIn('match_status', ['unmatched', 'error'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'transactions' => $rows,
                'totals' => [
                    'count'  => $rows->count(),
                    'amount' => (float) $rows->sum('amount'),
                ],
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/reconciliation/transactions/{id}/match",
     *     summary="Manually match an unmatched transaction to a donor (creates a donation)",
     *     tags={"Reconciliation"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"donor_id"},
     *         @OA\Property(property="donor_id", type="integer"),
     *         @OA\Property(property="project_id", type="integer")
     *     )),
     *     @OA\Response(response=200, description="Transaction matched")
     * )
     */
    public function matchTransaction(Request $request, Transaction $transaction)
    {
        $request->validate([
            'donor_id'   => 'required|exists:donors,id',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $txn   = BankTransaction::findOrFail($transaction);
        $donor = Donor::findOrFail($request->donor_id);

        try {
            $txn = $this->service->manualMatch($txn, $donor, $request->project_id);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction matched and donation created.',
            'data'    => $txn,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/reconciliation/uploads/{id}",
     *     summary="Delete an upload (cascades transactions; donations created from it stay)",
     *     tags={"Reconciliation"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function destroy($id)
    {
        $upload = BankStatementUpload::findOrFail($id);

        if ($upload->stored_path && Storage::exists($upload->stored_path)) {
            Storage::delete($upload->stored_path);
        }
        $upload->delete();

        return response()->json([
            'success' => true,
            'message' => 'Upload deleted successfully!',
        ]);
    }
}
