<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDonorSourceRequest;
use App\Http\Requests\UpdateDonorSourceRequest;
use App\Models\DonorSource;
use Illuminate\Http\Request;

class DonorSourceController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/donor-sources",
     * summary="List donor sources with search and status filtering",
     * tags={"Donor Sources"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string"), description="Search by name or description"),
     * @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="boolean"), description="Filter by active status (true/false)"),
     * @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $query = DonorSource::query()
            ->withCount('donors'); // how many doner have under this donor source

        // search filter Name or Description 
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // active/inactive filter
        if ($request->has('is_active')) {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        $sources = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $sources,
        ], 200);
    }

    /**
     * @OA\Post(
     * path="/api/donor-sources",
     * summary="Create a new donor source",
     * tags={"Donor Sources"},
     * security={{"sanctum":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"name","is_active"},
     * @OA\Property(property="name", type="string", example="Website Signup"),
     * @OA\Property(property="description", type="string", example="Donors who registered directly from the main web portal"),
     * @OA\Property(property="is_active", type="boolean", example=true)
     * )
     * ),
     * @OA\Response(response=201, description="Donor source created"),
     * @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreDonorSourceRequest $request)
    {
        
        $source = DonorSource::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Donor source created successfully!',
            'data'    => $source->loadCount('donors'),
        ], 201);
    }

    /**
     * @OA\Get(
     * path="/api/donor-sources/{id}",
     * summary="Get details of a specific donor source with its donors",
     * tags={"Donor Sources"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Successful operation"),
     * @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $source = DonorSource::with(['donors' => fn($q) => $q->latest()])
            ->withCount('donors')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $source,
        ], 200);
    }

    /**
     * @OA\Put(
     * path="/api/donor-sources/{id}",
     * summary="Update a donor source",
     * tags={"Donor Sources"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\RequestBody(required=true, @OA\JsonContent(type="object")),
     * @OA\Response(response=200, description="Donor source updated"),
     * @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateDonorSourceRequest $request, $id)
    {
        $source = DonorSource::findOrFail($id);
        $source->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Donor source updated successfully!',
            'data'    => $source->loadCount('donors'),
        ], 200);
    }

    /**
     * @OA\Delete(
     * path="/api/donor-sources/{id}",
     * summary="Delete a donor source (blocked if linked to any donors)",
     * tags={"Donor Sources"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Donor source deleted"),
     * @OA\Response(response=409, description="Source has linked donors")
     * )
     */
    public function destroy($id)
    {
        $source = DonorSource::findOrFail($id);

     
        if ($source->donors()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete: this donor source is currently assigned to active donors.',
            ], 409);
        }

        $source->delete();

        return response()->json([
            'success' => true,
            'message' => 'Donor source deleted successfully!',
        ], 200);
    }
}