<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoProjectSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('email', 'admin@gmail.com')->first()
            ?? User::first();

        if (!$creator) {
            return;
        }

        $typeId = fn (string $name) => ProjectType::where('name', $name)->value('id');

        $projects = [
            [
                'project_type_id' => $typeId('Zakat'),
                'name'            => 'Ramadan Food Distribution 2026',
                'project_code'    => 'PRJ-RAMADAN-2026',
                'description'     => 'Distribute iftar and sehri food packages to 500 families across Dhaka.',
                'budget'          => 750000,
                'status'          => 'active',
            ],
            [
                'project_type_id' => $typeId('Education'),
                'name'            => 'Madrasah Scholarship Program',
                'project_code'    => 'PRJ-EDU-SCHOLAR',
                'description'     => 'Annual scholarships for 50 underprivileged madrasah students.',
                'budget'          => 1200000,
                'status'          => 'active',
            ],
            [
                'project_type_id' => $typeId('Medical'),
                'name'            => 'Emergency Medical Aid Fund',
                'project_code'    => 'PRJ-MED-EMRG',
                'description'     => 'On-demand medical assistance for accident victims and critical patients.',
                'budget'          => 500000,
                'status'          => 'active',
            ],
            [
                'project_type_id' => $typeId('Sadaqah'),
                'name'            => 'Winter Blanket Drive 2025',
                'project_code'    => 'PRJ-WINTER-2025',
                'description'     => 'Distributed 2000 blankets to homeless and slum-dwellers last winter.',
                'budget'          => 300000,
                'status'          => 'completed',
            ],
            [
                'project_type_id' => $typeId('Others'),
                'name'            => 'Tube-well Installation - Rural',
                'project_code'    => 'PRJ-WATER-001',
                'description'     => 'Install 10 deep tube-wells in arsenic-affected villages.',
                'budget'          => 450000,
                'status'          => 'pending',
            ],
        ];

        foreach ($projects as $row) {
            if (!$row['project_type_id']) {
                continue;
            }
            Project::updateOrCreate(
                ['project_code' => $row['project_code']],
                array_merge($row, ['created_by' => $creator->id])
            );
        }
    }
}
