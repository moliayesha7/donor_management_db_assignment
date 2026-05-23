<?php

namespace Database\Seeders;

use App\Models\Campaign;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = [
            ['name' => 'Zakat',          'code' => 'ZKT', 'type' => 'one_off',   'default_amount' => null],
            ['name' => 'Fitra',          'code' => 'FTR', 'type' => 'one_off',   'default_amount' => null],
            ['name' => 'Sadaqah',        'code' => 'SDQ', 'type' => 'one_off',   'default_amount' => null],
            ['name' => 'Monthly Pledge', 'code' => 'MPL', 'type' => 'recurring', 'default_amount' => 10],
        ];

        foreach ($campaigns as $c) {
            Campaign::updateOrCreate(['name' => $c['name']], array_merge($c, ['is_active' => true]));
        }
    }
}
