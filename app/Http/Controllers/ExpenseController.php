<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/expenses",
     *     summary="List expenses with filters",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="project_id", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending","approved","paid"})),
     *     @OA\Parameter(name="from", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string"), description="Match vendor or description"),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $query = Expense::query()->with([
            'project:id,name,project_code',
            'creator:id,name',
        ]);

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('expense_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('expense_date', '<=', $to);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('vendor', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $expenses = $query->orderByDesc('expense_date')->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'expenses' => $expenses,
                'totals'   => [
                    'amount' => (float) $expenses->sum('amount'),
                    'count'  => $expenses->count(),
                ],
                'categories' => Expense::CATEGORIES,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/expenses",
     *     summary="Create an expense",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"project_id","category","amount","expense_date"},
     *             @OA\Property(property="project_id", type="integer", example=1),
     *             @OA\Property(property="category", type="string", example="Supplies"),
     *             @OA\Property(property="amount", type="number", format="float", example=1250.50),
     *             @OA\Property(property="expense_date", type="string", format="date", example="2026-05-18"),
     *             @OA\Property(property="vendor", type="string", example="Karim Stationery"),
     *             @OA\Property(property="description", type="string", example="Office supplies for project office"),
     *             @OA\Property(property="status", type="string", enum={"pending","approved","paid"}, example="approved")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Expense created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    // public function store(StoreExpenseRequest $request)
    // {
    //     $expense = Expense::create(array_merge(
    //         $request->validated(),
    //         [
    //             'status'     => $request->status ?? 'approved',
    //             'created_by' => $request->user()->id,
    //         ]
    //     ));

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Expense recorded successfully!',
    //         'data'    => $expense->load(['project:id,name,project_code', 'creator:id,name']),
    //     ], 201);
    // }
    public function store(StoreExpenseRequest $request)
    {
        $expense = Expense::create(array_merge(
            $request->validated(),
            [
                'status'     => $request->status ?? 'approved',
                'created_by' => $request->user()->id,
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Expense recorded successfully!',
            'data'    => $expense->load(['project:id,name,project_code', 'creator:id,name']),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/expenses/{id}",
     *     summary="Get a single expense",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    // public function show($id)
    // {
    //     $expense = Expense::with(['project:id,name,project_code', 'creator:id,name'])->findOrFail($id);

    //     return response()->json([
    //         'success' => true,
    //         'data'    => $expense,
    //     ]);
    // }
    public function show(Expense $expense)
    {
        $expense->load(['project:id,name,project_code', 'creator:id,name']);

        return response()->json([
            'success' => true,
            'data'    => $expense,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/expenses/{id}",
     *     summary="Update an expense",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object")),
     *     @OA\Response(response=200, description="Expense updated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    // public function update(UpdateExpenseRequest $request, $id)
    // {
    //     $expense = Expense::findOrFail($id);
    //     $expense->update($request->validated());

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Expense updated successfully!',
    //         'data'    => $expense->load(['project:id,name,project_code', 'creator:id,name']),
    //     ]);
    // }
    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $expense->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully!',
            'data'    => $expense->load(['project:id,name,project_code', 'creator:id,name']),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/expenses/{id}",
     *     summary="Delete an expense",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Expense deleted")
     * )
     */
    // public function destroy($id)
    // {
    //     $expense = Expense::findOrFail($id);
    //     $expense->delete();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Expense deleted successfully!',
    //     ]);
    // }
    public function destroy(Expense $expense)
    {
        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully!',
        ]);
    }
}
