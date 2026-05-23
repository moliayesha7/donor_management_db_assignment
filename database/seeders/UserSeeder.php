<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * One demo user per role. Password for every demo account: "password"
     * (overrideable per row via the 'password' key).
     */
    public static function demoUsers(): array
    {
        return [
            [
                'role'  => 'super_admin',
                'name'  => 'Super Admin',
                'email' => 'super@gmail.com',
            ],
            [
                'role'     => 'admin',
                'name'     => 'Admin User',
                'email'    => 'admin@gmail.com',
                'password' => '12345678',
            ],
            [
                'role'  => 'accountant',
                'name'  => 'Accountant Collector',
                'email' => 'accountant@gmail.com',
            ],
            [
                'role'  => 'user',
                'name'  => 'Field Volunteer',
                'email' => 'user@gmail.com',
            ],
        ];
    }

    public function run(): void
    {
        $roleIds = Role::pluck('id', 'name');

        foreach (self::demoUsers() as $row) {
            User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'role_id'  => $roleIds[$row['role']] ?? null,
                    'name'     => $row['name'],
                    'password' => Hash::make($row['password'] ?? 'password'),
                    'status'   => 'active',
                ]
            );
        }
    }
}
