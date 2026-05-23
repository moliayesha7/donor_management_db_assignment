<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    /**
     * List students with search by name / student_id / post_code, and
     * filter by funding_status.
     */
   public function index(Request $request)
{
    $query = Student::query()->withCount('donations');

    // ১. গ্লোবাল সার্চ ফিল্টার (যদি ফ্রন্টএন্ড থেকে একটি সিঙ্গেল ইনপুট দিয়ে সব খোঁজা হয়)
    if ($search = $request->query('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('student_name', 'like', "%{$search}%") // 'name' এর বদলে 'student_name'
              ->orWhere('student_id', 'like', "%{$search}%") // 'student_id' এর বদলে 'student_id'
              ->orWhere('post_code', 'like', "%{$search}%");
        });
    }

    // ২. স্পেসিফিক ফিল্টার (আলাদা আলাদা ইনপুট ফিল্ডের জন্য)
    if ($studentName = $request->query('student_name')) {
        $query->where('student_name', 'like', "%{$studentName}%");
    }

    if ($studentId = $request->query('student_id')) {
        $query->where('student_id', 'like', "%{$studentId}%"); // এখানে 'student_id' এর বদলে 'student_id' দিয়ে ফিক্স করা হয়েছে
    }

    if ($postCode = $request->query('post_code')) {
        $query->where('post_code', 'like', "%{$postCode}%");
    }

    if ($status = $request->query('funding_status')) {
        $query->where('funding_status', $status);
    }

    return response()->json([
        'success' => true,
        'data'    => $query->latest()->get(),
    ], 200);
}

    // public function store(StoreStudentRequest $request)
    // {
    //     $student = DB::transaction(function () use ($request) {
    //         return Student::create(array_merge(
    //             $request->validated(),
    //             [
    //                 'student_id' => $this->nextStudentIdCode(),
    //                 'funding_status'  => $request->input('funding_status', 'unfunded'),
    //                 'created_by'      => $request->user()->id,
    //             ]
    //         ));
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Student profile created successfully',
    //         'data'    => $student,
    //     ], 201);
    // }
    public function store(StoreStudentRequest $request)
    {
        $student = DB::transaction(function () use ($request) {
            return Student::create(array_merge(
                $request->validated(),
                [
                    'student_id'     => $this->nextStudentIdCode(),
                    'funding_status' => $request->input('funding_status', 'unfunded'),
                    'created_by'     => $request->user()->id,
                ]
            ));
        });

        return response()->json([
            'success' => true,
            'message' => 'Student profile created successfully',
            'data'    => $student,
        ], 201);
    }

    // public function show($id)
    // {
    //     $student = Student::with(['donations' => fn ($q) => $q->latest('transaction_date'), 'donations.donor:id,name,donor_id_code', 'donations.project:id,name,project_code'])
    //         ->withCount('donations')
    //         ->withSum('donations as donations_total', 'amount')
    //         ->findOrFail($id);

    //     return response()->json([
    //         'success' => true,
    //         'data'    => $student,
    //     ], 200);
    // }
    public function show(Student $student)
    {
        $student->load([
            'donations' => fn ($q) => $q->latest('transaction_date'),
            'donations.donor:id,name,donor_id_code',
            'donations.project:id,name,project_code'
        ])
        ->append(['donations_count', 'donations_sum_donations_total']);

        return response()->json([
            'success' => true,
            'data'    => $student,
        ], 200);
    }

    public function update(UpdateStudentRequest $request, Student $student)
    {
        $student->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Student profile updated successfully',
            'data'    => $student,
        ], 200);
    }

    // public function update(UpdateStudentRequest $request, $id)
    // {
    //     $student = Student::findOrFail($id);
    //     $student->update($request->validated());

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Student profile updated successfully',
    //         'data'    => $student,
    //     ], 200);
    // }

    // public function destroy($id)
    // {
    //     $student = Student::findOrFail($id);

    //     if ($student->donations()->exists()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Cannot delete: this student has donation history.',
    //         ], 409);
    //     }

    //     $student->delete();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Student profile deleted successfully',
    //     ], 200);
    // }

    public function destroy(Student $student)
    {
        if ($student->donations()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete: this student has donation history.',
            ], 409);
        }

        $student->delete();

        return response()->json([
            'success' => true,
            'message' => 'Student profile deleted successfully',
        ], 200);
    }

    /**
     * Sequential student code in the format STD-2001, STD-2002, ...
     */
    protected function nextStudentIdCode(): string
    {
        $last = Student::orderByDesc('id')->value('student_id');
        $lastNumber = 2000;

        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $lastNumber = max($lastNumber, (int) $m[1]);
        }

        return 'STD-' . ($lastNumber + 1);
    }
}
