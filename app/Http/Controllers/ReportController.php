<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/reports/project-wise",
     *     summary="Per-project totals: donations raised, donors, students, remaining vs budget",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending","active","completed","suspended"})),
     *     @OA\Parameter(name="project_type_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function projectWise(Request $request)
    {
        // donation.amount is encrypted at rest, so SQL SUM/COUNT-DISTINCT subqueries
        // are computed in PHP after loading the rows. Expenses stay SQL-side.
        $query = Project::query()
            ->with('type:id,name')
            ->select('projects.*')
            ->selectSub(
                Expense::selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('project_id', 'projects.id'),
                'expenses_total'
            )
            ->selectSub(
                Expense::selectRaw('COUNT(*)')
                    ->whereColumn('project_id', 'projects.id'),
                'expense_count'
            );

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($typeId = $request->query('project_type_id')) {
            $query->where('project_type_id', $typeId);
        }

        $projectRows = $query->orderBy('name')->get();

        $donationStats = $this->donationStatsByProject($projectRows->pluck('id')->all());

        $projects = $projectRows->map(function ($p) use ($donationStats) {
            $stats = $donationStats[$p->id] ?? ['total' => 0.0, 'donation_count' => 0, 'donor_count' => 0, 'student_count' => 0];
            $budget   = (float) $p->budget;
            $raised   = $stats['total'];
            $expenses = (float) $p->expenses_total;
            $p->donations_total = $raised;
            $p->donor_count     = $stats['donor_count'];
            $p->student_count   = $stats['student_count'];
            $p->donation_count  = $stats['donation_count'];
            return [
                'id'              => $p->id,
                'project_code'    => $p->project_code,
                'name'            => $p->name,
                'type'            => $p->type?->name,
                'status'          => $p->status,
                'budget'          => $budget,
                'donations_total' => $raised,
                'expenses_total'  => $expenses,
                'remaining'       => $budget - $expenses,           // Budget - Expenses (spec)
                'cash_on_hand'    => $raised - $expenses,            // Donations - Expenses
                'funded_percent'  => $budget > 0 ? round(($raised / $budget) * 100, 2) : 0,
                'spent_percent'   => $budget > 0 ? round(($expenses / $budget) * 100, 2) : 0,
                'donation_count'  => (int) $p->donation_count,
                'donor_count'     => (int) $p->donor_count,
                'student_count'   => (int) $p->student_count,
                'expense_count'   => (int) $p->expense_count,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'projects' => $projects,
                'totals'   => [
                    'budget_total'    => (float) $projects->sum('budget'),
                    'donations_total' => (float) $projects->sum('donations_total'),
                    'expenses_total'  => (float) $projects->sum('expenses_total'),
                    'remaining_total' => (float) $projects->sum('remaining'),
                    'cash_on_hand'    => (float) $projects->sum('cash_on_hand'),
                    'project_count'   => $projects->count(),
                ],
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/project/{id}/detail",
     *     summary="Detailed report for a single project: donor list, student list, recent donations",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Project not found")
     * )
     */
    public function projectDetail($id)
    {
        $project = Project::with('type:id,name')->findOrFail($id);

        // Pull all donations for this project once, then aggregate in PHP because
        // amounts are encrypted at rest. Eager-load donor + student so the cast
        // accessors fire and we never expose ciphertext.
        $projectDonations = Donation::with([
                'donor:id,donor_id_code,name,phone_number,email',
                'student:id,student_id,student_name,guardian_name,funding_status',
            ])
            ->where('project_id', $id)
            ->get();

        $donorList = $projectDonations
            ->filter(fn ($d) => $d->donor)
            ->groupBy('donor_id')
            ->map(function ($group) {
                $donor = $group->first()->donor;
                return [
                    'id'               => $donor->id,
                    'donor_id_code'    => $donor->donor_id_code,
                    'name'             => $donor->name,
                    'phone_number'     => $donor->phone_number,
                    'email'            => $donor->email,
                    'total_contributed'=> (float) $group->sum('amount'),
                    'donation_count'   => $group->count(),
                    'last_donation_at' => $group->max('transaction_date'),
                ];
            })
            ->sortByDesc('total_contributed')
            ->values();

        $studentList = $projectDonations
            ->whereNotNull('student_id')
            ->groupBy('student_id')
            ->map(function ($group) {
                $student = $group->first()->student;
                return [
                    'id'             => $student->id,
                    'student_id'     => $student->student_id,
                    'student_name'   => $student->student_name,
                    'guardian_name'  => $student->guardian_name,
                    'funding_status' => $student->funding_status,
                    'total_received' => (float) $group->sum('amount'),
                    'donation_count' => $group->count(),
                ];
            })
            ->sortByDesc('total_received')
            ->values();

        // Recent donations (latest 25) — reuse the already-loaded collection.
        $recentDonations = $projectDonations
            ->sortByDesc(fn ($d) => $d->transaction_date)
            ->take(25)
            ->values();

        $budget   = (float) $project->budget;
        $raised   = (float) $projectDonations->sum('amount');
        $expenses = (float) Expense::where('project_id', $id)->sum('amount');

        // Per-category expense breakdown (the spec's "Expense Breakdown")
        $expenseBreakdown = Expense::query()
            ->where('project_id', $id)
            ->select('category', DB::raw('SUM(amount) AS total_amount'), DB::raw('COUNT(*) AS expense_count'))
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get();

        // Recent expenses for the detail table
        $recentExpenses = Expense::query()
            ->where('project_id', $id)
            ->with('creator:id,name')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'project'    => [
                    'id'           => $project->id,
                    'project_code' => $project->project_code,
                    'name'         => $project->name,
                    'type'         => $project->type?->name,
                    'status'       => $project->status,
                    'budget'       => $budget,
                    'description'  => $project->description,
                ],
                'summary' => [
                    'donations_total' => $raised,
                    'expenses_total'  => $expenses,
                    'remaining'       => $budget - $expenses,       // Budget - Expenses (spec)
                    'cash_on_hand'    => $raised - $expenses,        // Donations - Expenses
                    'funded_percent'  => $budget > 0 ? round(($raised / $budget) * 100, 2) : 0,
                    'spent_percent'   => $budget > 0 ? round(($expenses / $budget) * 100, 2) : 0,
                    'donor_count'     => $donorList->count(),
                    'student_count'   => $studentList->count(),
                    'donation_count'  => $projectDonations->count(),
                    'expense_count'   => Expense::where('project_id', $id)->count(),
                ],
                'donors'             => $donorList,
                'students'           => $studentList,
                'recent_donations'   => $recentDonations,
                'expense_breakdown'  => $expenseBreakdown,
                'recent_expenses'    => $recentExpenses,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/donation-summary",
     *     summary="Donation summary: monthly (last 12), yearly, and top donors",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="from", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function donationSummary(Request $request)
    {
        $from = $request->query('from');
        $to   = $request->query('to');

        $base = Donation::query();
        if ($from) $base->whereDate('transaction_date', '>=', $from);
        if ($to)   $base->whereDate('transaction_date', '<=', $to);

        // amount is encrypted → load minimal columns + aggregate in PHP.
        $rows = (clone $base)->get(['id', 'donor_id', 'status', 'transaction_date', 'amount']);

        $bucket = function ($collection, callable $keyFn) {
            return $collection->groupBy($keyFn)->map(fn ($g) => [
                'donation_count' => $g->count(),
                'total_amount'   => (float) $g->sum('amount'),
            ])->sortKeys()->map(fn ($v, $k) => ['period' => (string) $k] + $v)->values();
        };

        $cutoff  = now()->subMonths(11)->startOfMonth();
        $monthly = $bucket(
            $rows->filter(fn ($d) => $d->transaction_date && $d->transaction_date->greaterThanOrEqualTo($cutoff)),
            fn ($d) => $d->transaction_date?->format('Y-m')
        );
        $yearly = $bucket($rows, fn ($d) => $d->transaction_date?->format('Y'));

        // Top 20 donors — group in PHP, then hydrate donor identity with decrypted casts.
        $byDonor = $rows->groupBy('donor_id')->map(fn ($g) => [
            'total_contributed' => (float) $g->sum('amount'),
            'donation_count'    => $g->count(),
            'last_donation_at'  => $g->max('transaction_date'),
        ])->sortByDesc('total_contributed')->take(20);

        $donorModels = Donor::whereIn('id', $byDonor->keys())->get(['id', 'donor_id_code', 'name', 'phone_number'])->keyBy('id');
        $topDonors = $byDonor->map(function ($stats, $donorId) use ($donorModels) {
            $d = $donorModels->get($donorId);
            return array_merge($stats, [
                'id'            => $donorId,
                'donor_id_code' => $d?->donor_id_code,
                'name'          => $d?->name,
                'phone_number'  => $d?->phone_number,
            ]);
        })->values();

        $byStatus = $rows->groupBy('status')->map(fn ($g, $status) => [
            'status'         => $status,
            'donation_count' => $g->count(),
            'total_amount'   => (float) $g->sum('amount'),
        ])->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'totals' => [
                    'donations_total' => (float) $rows->sum('amount'),
                    'donation_count'  => $rows->count(),
                    'unique_donors'   => $rows->pluck('donor_id')->unique()->count(),
                ],
                'monthly'    => $monthly,
                'yearly'     => $yearly,
                'top_donors' => $topDonors,
                'by_status'  => $byStatus,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/cash-flow",
     *     summary="Cash flow: monthly inflow (donations) vs outflow (expenses) + net + running balance",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="from", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function cashFlow(Request $request)
    {
        $from = $request->query('from');
        $to   = $request->query('to');

        $donationQuery = Donation::query();
        $expenseQuery  = Expense::query();
        if ($from) {
            $donationQuery->whereDate('transaction_date', '>=', $from);
            $expenseQuery->whereDate('expense_date', '>=', $from);
        }
        if ($to) {
            $donationQuery->whereDate('transaction_date', '<=', $to);
            $expenseQuery->whereDate('expense_date', '<=', $to);
        }

        // amount is encrypted → group by period in PHP after fetching.
        $donationsByMonth = (clone $donationQuery)->get(['transaction_date', 'amount'])
            ->groupBy(fn ($d) => $d->transaction_date?->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('amount'))
            ->all();

        $expensesByMonth = (clone $expenseQuery)
            ->select(DB::raw("DATE_FORMAT(expense_date, '%Y-%m') AS period"), DB::raw('SUM(amount) AS amount'))
            ->groupBy('period')->pluck('amount', 'period')->all();

        $months = array_unique(array_merge(array_keys($donationsByMonth), array_keys($expensesByMonth)));
        sort($months);

        $running = 0;
        $rows = [];
        foreach ($months as $m) {
            $in  = (float) ($donationsByMonth[$m] ?? 0);
            $out = (float) ($expensesByMonth[$m] ?? 0);
            $net = $in - $out;
            $running += $net;
            $rows[] = [
                'period'    => $m,
                'inflow'    => $in,
                'outflow'   => $out,
                'net'       => $net,
                'balance'   => $running,
            ];
        }

        $totalIn  = (float) array_sum($donationsByMonth);
        $totalOut = (float) array_sum($expensesByMonth);

        return response()->json([
            'success' => true,
            'data'    => [
                'rows'   => $rows,
                'totals' => [
                    'inflow'      => $totalIn,
                    'outflow'     => $totalOut,
                    'net'         => $totalIn - $totalOut,
                    'closing_balance' => $running,
                ],
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/donation-ledger",
     *     summary="Chronological donation ledger with running balance",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="from", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="donor_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="project_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function donationLedger(Request $request)
    {
        $query = Donation::with([
            'donor:id,donor_id_code,name',
            'project:id,project_code,name',
            'student:id,student_name',
        ])->orderBy('transaction_date')->orderBy('id');

        if ($from = $request->query('from'))    $query->whereDate('transaction_date', '>=', $from);
        if ($to = $request->query('to'))        $query->whereDate('transaction_date', '<=', $to);
        if ($d = $request->query('donor_id'))   $query->where('donor_id', $d);
        if ($p = $request->query('project_id')) $query->where('project_id', $p);

        $donations = $query->get();

        $running = 0;
        $rows = $donations->map(function ($d) use (&$running) {
            $running += (float) $d->amount;
            return [
                'id'               => $d->id,
                'transaction_date' => $d->transaction_date,
                'receipt_number'   => $d->receipt_number,
                'donor'            => $d->donor ? [
                    'id'            => $d->donor->id,
                    'donor_id_code' => $d->donor->donor_id_code,
                    'name'          => $d->donor->name,
                ] : null,
                'project'  => $d->project ? ['name' => $d->project->name, 'project_code' => $d->project->project_code] : null,
                'student'  => $d->student?->student_name,
                'amount'   => (float) $d->amount,
                'method'   => $d->payment_method,
                'status'   => $d->status,
                'balance'  => $running,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'rows'   => $rows,
                'totals' => [
                    'count'         => $rows->count(),
                    'amount'        => (float) $donations->sum('amount'),
                    'closing_balance' => $running,
                ],
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/project-balance",
     *     summary="Per-project balance: budget vs raised vs spent + remaining + cash-on-hand",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function projectBalance()
    {
        $rows = Project::with('type:id,name')
            ->select('projects.*')
            ->selectSub(
                Expense::selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('project_id', 'projects.id'),
                'expenses_total'
            )
            ->orderBy('name')
            ->get();

        $donationTotals = $this->donationStatsByProject($rows->pluck('id')->all());

        $projects = $rows->map(function ($p) use ($donationTotals) {
                $budget   = (float) $p->budget;
                $raised   = (float) ($donationTotals[$p->id]['total'] ?? 0);
                $expenses = (float) $p->expenses_total;
                $cash     = $raised - $expenses;
                return [
                    'id'             => $p->id,
                    'project_code'   => $p->project_code,
                    'name'           => $p->name,
                    'type'           => $p->type?->name,
                    'status'         => $p->status,
                    'budget'         => $budget,
                    'donations'      => $raised,
                    'expenses'       => $expenses,
                    'remaining'      => $budget - $expenses,
                    'cash_on_hand'   => $cash,
                    'overspent'      => $cash < 0,
                    'utilization'    => $budget > 0 ? round(($expenses / $budget) * 100, 2) : 0,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => [
                'rows'   => $projects,
                'totals' => [
                    'budget'       => (float) $projects->sum('budget'),
                    'donations'    => (float) $projects->sum('donations'),
                    'expenses'     => (float) $projects->sum('expenses'),
                    'remaining'    => (float) $projects->sum('remaining'),
                    'cash_on_hand' => (float) $projects->sum('cash_on_hand'),
                ],
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/financial-reconciliation",
     *     summary="Reconciliation report: reconciled vs unreconciled bank transactions + match-status breakdown",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function financialReconciliation()
    {
        $byStatus = BankTransaction::query()
            ->select('match_status', DB::raw('COUNT(*) AS count'), DB::raw('SUM(amount) AS amount'))
            ->groupBy('match_status')
            ->get()
            ->keyBy('match_status');

        $get = fn (string $key) => [
            'count'  => (int) ($byStatus[$key]->count ?? 0),
            'amount' => (float) ($byStatus[$key]->amount ?? 0),
        ];

        $matched      = $get('matched');
        $donorCreated = $get('donor_created');
        $unmatched    = $get('unmatched');
        $duplicate    = $get('duplicate');
        $error        = $get('error');
        $skipped      = $get('skipped');

        $reconciled    = ['count' => $matched['count'] + $donorCreated['count'], 'amount' => $matched['amount'] + $donorCreated['amount']];
        $unreconciled  = ['count' => $unmatched['count'] + $error['count'],      'amount' => $unmatched['amount'] + $error['amount']];

        // Per-upload roll-up
        $uploads = \App\Models\BankStatementUpload::latest()->get()->map(fn ($u) => [
            'id'              => $u->id,
            'original_name'   => $u->original_name,
            'created_at'      => $u->created_at,
            'total_rows'      => $u->total_rows,
            'matched_rows'    => $u->matched_rows,
            'unmatched_rows'  => $u->unmatched_rows,
            'duplicate_rows'  => $u->duplicate_rows,
            'error_rows'      => $u->error_rows,
            'total_amount'    => (float) $u->total_amount,
            'reconcile_rate'  => $u->total_rows > 0 ? round((($u->matched_rows + $u->donor_created_rows) / $u->total_rows) * 100, 2) : 0,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'summary' => [
                    'reconciled'   => $reconciled,
                    'unreconciled' => $unreconciled,
                    'matched'      => $matched,
                    'donor_created'=> $donorCreated,
                    'unmatched'    => $unmatched,
                    'duplicate'    => $duplicate,
                    'error'        => $error,
                    'skipped'      => $skipped,
                ],
                'uploads' => $uploads,
            ],
        ]);
    }

    /**
     * Sum encrypted donation amounts per project_id and count distinct donors/students.
     * Returns: [project_id => ['total' => float, 'donation_count' => int, 'donor_count' => int, 'student_count' => int]]
     *
     * SQL aggregation is impossible because donations.amount is encrypted ciphertext
     * (every encrypt() uses a fresh IV — column values are non-deterministic). We
     * pull every matching row in a single query and reduce in PHP.
     */
    protected function donationStatsByProject(array $projectIds): array
    {
        if (empty($projectIds)) return [];

        $rows = Donation::query()
            ->whereIn('project_id', $projectIds)
            ->get(['id', 'project_id', 'donor_id', 'student_id', 'amount']);

        $stats = [];
        foreach ($rows->groupBy('project_id') as $projectId => $group) {
            $stats[$projectId] = [
                'total'          => (float) $group->sum('amount'),
                'donation_count' => $group->count(),
                'donor_count'    => $group->pluck('donor_id')->unique()->count(),
                'student_count'  => $group->whereNotNull('student_id')->pluck('student_id')->unique()->count(),
            ];
        }
        return $stats;
    }
}
