<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,      // 1. permissions catalog
            RoleSeeder::class,            // 2. roles + role->permission attach (needs permissions)
            UserSeeder::class,            // 3. one demo user per role
            ProjectTypeSeeder::class,     // 4. seed types [Zakat, Sadaqah, Education, Medical, Others]
            DemoProjectSeeder::class,     // 5. demo projects across types
            DonorSourceSeeder::class,
            DemoDonorSeeder::class,       // 6. demo donors (some with preferred project)
            StudentSeeder::class,         // 7. demo students (Part 2)
            CampaignSeeder::class,        // 7b. default campaigns (Zakat / Fitra / Sadaqah / Monthly Pledge)
            DonationSeeder::class,        // 8. demo donations linking donors/projects/students (Part 5)
            DemoExpenseSeeder::class,     // 9. demo expenses across seeded projects
            DemoEmailTrackerSeeder::class,
        ]);
    }
}
