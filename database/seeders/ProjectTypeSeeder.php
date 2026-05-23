<?php

namespace Database\Seeders;

use App\Models\ProjectType;
use Illuminate\Database\Seeder;

class ProjectTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['Zakat', 'Sadaqah', 'Education', 'Medical', 'Others'];

        foreach ($types as $type) {
            ProjectType::updateOrCreate(
                ['name' => $type],
                ['status' => 'active']
            );
        }
    }
}