<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('email', 'admin@gmail.com')->first() ?? User::first();
        if (!$creator) {
            return;
        }

        $projectId = fn (string $code) => Project::where('project_code', $code)->value('id');

        $expenses = [
            // Ramadan project — large food distribution running expenses
            [
                'project_code' => 'PRJ-RAMADAN-2026',
                'category'     => 'Supplies',
                'amount'       => 45000,
                'expense_date' => '2026-04-02',
                'vendor'       => 'Karim Wholesale Rice',
                'description'  => 'Rice (500kg) for iftar packages',
                'status'       => 'paid',
            ],
            [
                'project_code' => 'PRJ-RAMADAN-2026',
                'category'     => 'Supplies',
                'amount'       => 28000,
                'expense_date' => '2026-04-05',
                'vendor'       => 'Halal Foods Ltd',
                'description'  => 'Cooking oil + dates + lentils',
                'status'       => 'paid',
            ],
            [
                'project_code' => 'PRJ-RAMADAN-2026',
                'category'     => 'Logistics',
                'amount'       => 12000,
                'expense_date' => '2026-04-08',
                'vendor'       => 'Dhaka Pickup Service',
                'description'  => 'Truck rental for distribution drop-offs (3 days)',
                'status'       => 'paid',
            ],
            [
                'project_code' => 'PRJ-RAMADAN-2026',
                'category'     => 'Salaries',
                'amount'       => 25000,
                'expense_date' => '2026-04-15',
                'vendor'       => null,
                'description'  => 'Volunteer stipends for 10 distribution staff',
                'status'       => 'approved',
            ],

            // Education / scholarship project
            [
                'project_code' => 'PRJ-EDU-SCHOLAR',
                'category'     => 'Salaries',
                'amount'       => 60000,
                'expense_date' => '2026-04-30',
                'vendor'       => null,
                'description'  => 'Monthly scholarship disbursement (Q2)',
                'status'       => 'paid',
            ],
            [
                'project_code' => 'PRJ-EDU-SCHOLAR',
                'category'     => 'Supplies',
                'amount'       => 8500,
                'expense_date' => '2026-05-03',
                'vendor'       => 'Boi Bazar',
                'description'  => 'Textbooks for 25 scholarship recipients',
                'status'       => 'paid',
            ],

            // Medical aid project
            [
                'project_code' => 'PRJ-MED-EMRG',
                'category'     => 'Equipment',
                'amount'       => 18000,
                'expense_date' => '2026-05-10',
                'vendor'       => 'MedSupply BD',
                'description'  => 'Emergency first-aid kits (50 units)',
                'status'       => 'paid',
            ],

            // Winter blanket drive (completed)
            [
                'project_code' => 'PRJ-WINTER-2025',
                'category'     => 'Supplies',
                'amount'       => 180000,
                'expense_date' => '2025-12-15',
                'vendor'       => 'Aarong Textiles',
                'description'  => '2000 blankets for distribution',
                'status'       => 'paid',
            ],
            [
                'project_code' => 'PRJ-WINTER-2025',
                'category'     => 'Logistics',
                'amount'       => 22000,
                'expense_date' => '2025-12-22',
                'vendor'       => 'Highway Transport',
                'description'  => 'Distribution across 5 districts',
                'status'       => 'paid',
            ],
        ];

        foreach ($expenses as $row) {
            $pid = $projectId($row['project_code']);
            if (!$pid) continue;
            // Idempotent by (project_id, vendor, expense_date, amount) — close enough
            // for demo data without adding a dedicated dedup column.
            Expense::firstOrCreate(
                [
                    'project_id'   => $pid,
                    'expense_date' => $row['expense_date'],
                    'amount'       => $row['amount'],
                    'category'     => $row['category'],
                ],
                [
                    'vendor'      => $row['vendor'] ?? null,
                    'description' => $row['description'] ?? null,
                    'status'      => $row['status'] ?? 'approved',
                    'created_by'  => $creator->id,
                ]
            );
        }
    }
}
