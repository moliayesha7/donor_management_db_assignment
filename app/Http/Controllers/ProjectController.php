<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *     title="Donor Management API Documentation",
 *     version="1.0.0",
 *     description="API endpoints for Donor Management System"
 * )
 * @OA\Server(
 *     url="http://127.0.0.1:8000",
 *     description="Primary Local API Server"
 * )
 */
class ProjectController extends Controller
{
    /**
     * Display a listing of the resource (GET /api/projects)
     *
     * @OA\Get(
     *     path="/api/projects",
     *     summary="List projects with optional search and status filter",
     *     tags={"Projects"},
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string"), description="Search by project name or code"),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending","active","completed","suspended"}), description="Filter by status"),
     *     @OA\Parameter(name="project_type_id", in="query", required=false, @OA\Schema(type="integer"), description="Filter by project type"),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $query = Project::with('type');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('project_code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($typeId = $request->query('project_type_id')) {
            $query->where('project_type_id', $typeId);
        }

        $projects = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $projects
        ], 200);
    }

    /**
     * Store a newly created resource in storage (POST /api/projects)
     *
     * @OA\Post(
     *     path="/api/projects",
     *     summary="Create a new project",
     *     tags={"Projects"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"project_type_id","name","project_code","budget"},
     *             @OA\Property(property="project_type_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Clean Water Initiative"),
     *             @OA\Property(property="project_code", type="string", example="PRJ-001"),
     *             @OA\Property(property="description", type="string", example="Optional description"),
     *             @OA\Property(property="budget", type="number", format="float", example=50000)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Project created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreProjectRequest $request)
    {
        $project = Project::create(array_merge(
            $request->validated(),
            [
                'status'     => 'pending',
                'created_by' => auth()->id() ?? User::value('id'),
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully!',
            'data'    => $project
        ], 201);
    }

    /**
     * Display the specified resource (GET /api/projects/{id})
     *
     * @OA\Get(
     *     path="/api/projects/{id}",
     *     summary="Get a single project",
     *     tags={"Projects"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Project not found")
     * )
     */
    public function show($id)
    {
        $project = Project::with('type')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $project
        ], 200);
    }

    /**
     * Update the specified resource in storage (PUT /api/projects/{id})
     *
     * @OA\Put(
     *     path="/api/projects/{id}",
     *     summary="Update an existing project",
     *     tags={"Projects"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object")),
     *     @OA\Response(response=200, description="Project updated successfully")
     * )
     */
    public function update(UpdateProjectRequest $request, $id)
    {
        $project = Project::findOrFail($id);
        $project->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully!',
            'data'    => $project
        ], 200);
    }

    /**
     * Remove the specified resource from storage (DELETE /api/projects/{id})
     *
     * @OA\Delete(
     *     path="/api/projects/{id}",
     *     summary="Delete a project",
     *     tags={"Projects"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Project deleted successfully")
     * )
     */
    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Project deleted successfully!'
        ], 200);
    }
}
