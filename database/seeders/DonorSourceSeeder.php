<?php

namespace Database\Seeders;

use App\Models\DonorSource;
use Illuminate\Database\Seeder;

class DonorSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            [
                'name' => 'Website',
                'description' => 'Donors who registered directly through our main web portal.',
                'is_active' => true,
            ],
            [
                'name' => 'Facebook Campaign',
                'description' => 'Donors acquired via social media advertisements.',
                'is_active' => true,
            ],
            [
                'name' => 'Bank Transfer',
                'description' => 'Direct manual bank wire transfers.',
                'is_active' => true,
            ],
            [
                'name' => 'Event/Fundraiser',
                'description' => 'Donations collected during physical events.',
                'is_active' => false, 
            ],
        ];

        foreach ($sources as $source) {
            DonorSource::updateOrCreate(['name' => $source['name']], $source);
        }
    }
}