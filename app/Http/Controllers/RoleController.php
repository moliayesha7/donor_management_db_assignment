<?php

namespace App\Http\Controllers;

use App\Models\Role;

class RoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/roles",
     *     summary="List all roles (for dropdowns)",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index()
    {
        $roles = Role::with('permissions')->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => $roles,
        ], 200);
    }
}
