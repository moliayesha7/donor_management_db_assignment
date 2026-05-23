<?php

namespace Database\Seeders;

use App\Models\Donation;
use App\Models\Donor;
use App\Models\Project;
use App\Models\Student;
use Illuminate\Database\Seeder;

class DonationSeeder extends Seeder
{
    public function run(): void
    {
        $donor = Donor::first();
        $project = Project::first();
        if (! $donor || ! $project) {
            return;
        }

        $student = Student::first();

        $rows = [
            [
                'donor_id'         => $donor->id,
                'project_id'       => $project->id,
                'student_id'       => $student?->id,
                'amount'           => 250.00,
                'payment_method'   => 'Stripe',
                'transaction_date' => now()->subDays(7),
                'receipt_number'   => 'REC-100001',
                'status'           => 'confirmed',
            ],
            [
                'donor_id'         => $donor->id,
                'project_id'       => $project->id,
                'student_id'       => null,
                'amount'           => 50.00,
                'payment_method'   => 'Card Payment',
                'transaction_date' => now()->subDays(3),
                'receipt_number'   => 'REC-100002',
                'status'           => 'confirmed',
            ],
        ];

        foreach ($rows as $row) {
            Donation::updateOrCreate(['receipt_number' => $row['receipt_number']], $row);
        }
    }
}
