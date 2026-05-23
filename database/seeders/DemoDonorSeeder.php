<?php

namespace Database\Seeders;

use App\Models\Donor;
use App\Models\DonorSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDonorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       
        $user = User::first() ?? User::factory()->create();
        $project = Project::first(); 
        $websiteSource = DonorSource::where('name', 'Website')->first();
        $facebookSource = DonorSource::where('name', 'Facebook Campaign')->first();

        $donors = [
            [
                'donor_id_code'        => 'DNR-1001',
                'name'                 => 'Ayesha Khatun',
                'address_lookup'       => 'Mirur, Dhaka',
                'address_line_1'       => 'Mirpur',
                'address_line_2'       => '',
                'address_line_3'       => '',
                'city'                 => 'Dhaka',
                'county'               => 'Bangladesh',
                'post_code'            => '1212',
                'phone_number'         => '+8801813235452',
                'email'                => 'ayeshabdtask@gmail.com',
                'country'              => 'Bangladesh',
                'donor_source_id'      => $websiteSource ? $websiteSource->id : null,
                'preferred_project_id' => $project ? $project->id : null,
                'created_by'           => $user->id,
            ],
            [
                'donor_id_code'        => 'DNR-1002',
                'name'                 => 'Rahim Ahmed',
                'address_lookup'       => 'Dhanmondi, Dhaka',
                'address_line_1'       => 'House 12, Road 5',
                'address_line_2'       => 'Dhanmondi',
                'address_line_3'       => '',
                'city'                 => 'Dhaka',
                'county'               => 'Dhaka Division',
                'post_code'            => '1209',
                'phone_number'         => '+8801813235452',
                'email'                => 'ayeshabdtask@gmail.com',
                'country'              => 'Bangladesh',
                'donor_source_id'      => $facebookSource ? $facebookSource->id : null,
                'preferred_project_id' => null,
                'created_by'           => $user->id,
            ]
        ];

        foreach ($donors as $donor) {
            Donor::updateOrCreate(['donor_id_code' => $donor['donor_id_code']], $donor);
        }
    }
}