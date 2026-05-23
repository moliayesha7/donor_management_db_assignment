<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDonorRequest;
use App\Http\Requests\UpdateDonorRequest;
use App\Models\Donation;
use App\Models\Donor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/donors",
     *     summary="List donors with search and filter",
     *     tags={"Donors"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string"), description="Search by name, donor_id_code, phone, or post_code"),
     *     @OA\Parameter(name="preferred_project_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
   public function index(Request $request)
    {
        $query = Donor::with([
            'preferredProject:id,name,project_code',
            'donorSource:id,name',
            'creator:id,name',
        ])->withCount('donations');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('donor_id_code', 'like', "%{$search}%")
                  ->orWhere('post_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('preferred_project_id')) {
            $query->where('preferred_project_id', $request->query('preferred_project_id'));
        }

        $donors = $query->latest()->get();

     
        $totals = Donation::whereIn('donor_id', $donors->pluck('id'))
            ->get(['donor_id', 'amount'])
            ->groupBy('donor_id')
            ->map(fn ($g) => (float) $g->sum('amount'));

        $donors->each(function ($d) use ($totals) {
            $d->donations_total = (float) ($totals[$d->id] ?? 0);
        });

        return response()->json(['success' => true, 'data' => $donors], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/donors",
     *     summary="Create a donor (donor_id_code auto-generated as DNR-1001, DNR-1002, ...)",
     *     tags={"Donors"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","phone","address","post_code"},
     *             @OA\Property(property="name", type="string", example="Rahim Ahmed"),
     *             @OA\Property(property="phone", type="string", example="+8801712345678"),
     *             @OA\Property(property="email", type="string", format="email", example="ayeshabdtask@gmail.com"),
     *             @OA\Property(property="address", type="string", example="House 12, Road 5, Dhanmondi"),
     *             @OA\Property(property="post_code", type="string", example="1209"),
     *             @OA\Property(property="preferred_project_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Donor created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    // public function store(StoreDonorRequest $request)
    // {
    //     $donor = DB::transaction(function () use ($request) {
    //         return Donor::create(array_merge(
    //             $request->validated(),
    //             [
    //                 'donor_id_code' => $this->nextDonorIdCode(),
    //                 'created_by'    => $request->user()->id,
    //             ]
    //         ));
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Donor created successfully!',
    //         'data'    => $donor->load(['preferredProject:id,name,project_code', 'donorSource:id,name', 'creator:id,name']),
    //     ], 201);
    // }

    public function store(StoreDonorRequest $request)
    {
        $donor = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['donor_id_code'] = $this->nextDonorIdCode();
            $data['created_by'] = $request->user()->id;
            
            return Donor::create($data);
        });

        return response()->json([
            'success' => true, 
            'message' => 'Donor created successfully!', 
            'data'    => $donor->load(['preferredProject:id,name,project_code', 'donorSource:id,name', 'creator:id,name'])
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/donors/{id}",
     *     summary="Get a donor with donation history",
     *     tags={"Donors"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="donor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    // public function show($id)
    // {
    //     $donor = Donor::with([
    //         'preferredProject:id,name,project_code',
    //         'donorSource:id,name',
    //         'creator:id,name',
    //         'donations' => fn ($q) => $q->latest('transaction_date'),
    //         'donations.project:id,name,project_code',
    //         'donations.student:id,student_name',
    //     ])
    //         ->withCount('donations')
    //         ->withSum('donations as donations_total', 'amount')
    //         ->findOrFail($id);

    //     return response()->json([
    //         'success' => true,
    //         'data'    => $donor,
    //     ], 200);
    // }

   public function show(Donor $donor)
    {
        $donor->load([
            'preferredProject:id,name,project_code',
            'donorSource:id,name',
            'creator:id,name',
            'donations' => fn ($q) => $q->latest('transaction_date'),
            'donations.project:id,name,project_code',
            'donations.student:id,student_name',
        ]);

        $donor->donations_count = $donor->donations->count();
        $donor->donations_total = (float) $donor->donations->sum('amount');

        return response()->json(['success' => true, 'data' => $donor], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/donors/{id}",
     *     summary="Update a donor",
     *     tags={"Donors"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="donor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object")),
     *     @OA\Response(response=200, description="Donor updated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    // public function update(UpdateDonorRequest $request, $id)
    // {
    //     $donor = Donor::findOrFail($id);
    //     $donor->update($request->validated());

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Donor updated successfully!',
    //         'data'    => $donor->load(['preferredProject:id,name,project_code', 'donorSource:id,name', 'creator:id,name']),
    //     ], 200);
    // }

    public function update(UpdateDonorRequest $request, Donor $donor)
    {
        $donor->update($request->validated());

        return response()->json([
            'success' => true, 
            'message' => 'Donor updated successfully!',
            'data'    => $donor->load(['preferredProject:id,name,project_code', 'donorSource:id,name', 'creator:id,name'])
        ], 200);
    }
    /**
     * @OA\Delete(
     *     path="/api/donors/{id}",
     *     summary="Delete a donor (blocked if donations exist)",
     *     tags={"Donors"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="donor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Donor deleted"),
     *     @OA\Response(response=409, description="Donor has donation history")
     * )
     */
    // public function destroy($id)
    // {
    //     $donor = Donor::findOrFail($id);

    //     if ($donor->donations()->exists()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Cannot delete: this donor has donation history.',
    //         ], 409);
    //     }

    //     $donor->delete();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Donor deleted successfully!',
    //     ], 200);
    // }

  public function destroy(Donor $donor)
    {
        if ($donor->donations()->exists()) {
            return response()->json([
                'success' => false, 
                'message' => 'Cannot delete: this donor has donation history.',
            ], 409);
        }

        $donor->delete();

        return response()->json(['success' => true, 'message' => 'Donor deleted successfully!'], 200);
    }

    /**
     * Generates the next donor_id_code in the format DNR-1001, DNR-1002, ...
     * Reads the numeric suffix of the highest existing code so we don't reuse IDs
     * after deletes.
     */
    protected function nextDonorIdCode(): string
    {
        $last = Donor::orderByDesc('id')->value('donor_id_code');
        $lastNumber = 1000;

        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $lastNumber = max($lastNumber, (int) $m[1]);
        }

        return 'DNR-' . ($lastNumber + 1);
    }
}
