<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectTypeRequest;
use App\Http\Requests\UpdateProjectTypeRequest;
use App\Models\ProjectType;
use Illuminate\Http\Request;

class ProjectTypeController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/project-types",
     * summary="List project types with optional search and status filter",
     * tags={"Project Types"},
     * @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string"), description="Search by name or description"),
     * @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"active","inactive"}), description="Filter by status"),
     * @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        // real-time count of projects under this type
        $query = ProjectType::query()->withCount('projects');

        // search by name or description
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $types = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $types
        ], 200);
    }

    /**
     * @OA\Post(
     * path="/api/project-types",
     * summary="Create a project type",
     * tags={"Project Types"},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"name"},
     * @OA\Property(property="name", type="string", example="Zakat"),
     * @OA\Property(property="description", type="string", example="Zakat fund allocation project type"),
     * @OA\Property(property="status", type="string", enum={"active","inactive"}, example="active")
     * )
     * ),
     * @OA\Response(response=201, description="Project type created"),
     * @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreProjectTypeRequest $request)
    {
        // use the validated data from the form request to save the description and status
        $type = ProjectType::create([
            'name'        => $request->name,
            'description' => $request->description, // for handling the description field in the screenshot
            'status'      => $request->status ?? 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project type created successfully!',
            'data'    => $type->loadCount('projects')
        ], 201);
    }

    /**
     * @OA\Get(
     * path="/api/project-types/{id}",
     * summary="Get a project type",
     * tags={"Project Types"},
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Successful operation"),
     * @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $type = ProjectType::withCount('projects')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $type
        ], 200);
    }

    /**
     * @OA\Put(
     * path="/api/project-types/{id}",
     * summary="Update a project type",
     * tags={"Project Types"},
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\RequestBody(required=true, @OA\JsonContent(type="object")),
     * @OA\Response(response=200, description="Project type updated"),
     * @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateProjectTypeRequest $request, $id)
    {
        $type = ProjectType::findOrFail($id);
        
        // use the validated data from the form request to update the description and status
        $type->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Project type updated successfully!',
            'data'    => $type->loadCount('projects')
        ], 200);
    }

    /**
     * @OA\Delete(
     * path="/api/project-types/{id}",
     * summary="Delete a project type",
     * tags={"Project Types"},
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Project type deleted"),
     * @OA\Response(response=409, description="Type has associated projects")
     * )
     */
    public function destroy($id)
    {
        $type = ProjectType::findOrFail($id);

        if ($type->projects()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete: this type has associated projects.'
            ], 409);
        }

        $type->delete();

        return response()->json([
            'success' => true,
            'message' => 'Project type deleted successfully!'
        ], 200);
    }
}