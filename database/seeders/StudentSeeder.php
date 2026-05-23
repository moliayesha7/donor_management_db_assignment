<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            [
                'student_id'        => 'STD-2001',
                'student_name'      => 'Aisha Rahman',
                'guardian_name'     => 'Fazlur Rahman',
                'guardian_phone'    => '+447123456789',
                'address'           => '123 Whitechapel Road, London',
                'post_code'         => 'E1 1DU',
                'educational_level' => 'Secondary',
                'institution_name'  => 'Oaklands School',
                'funding_status'    => 'fully_funded',
            ],
            [
                'student_id'        => 'STD-2002',
                'student_name'      => 'Zayan Malik',
                'guardian_name'     => 'Yousuf Malik',
                'guardian_phone'    => '+447987654321',
                'address'           => '45 Birmingham Road, Birmingham',
                'post_code'         => 'B1 1AY',
                'educational_level' => 'Primary',
                'institution_name'  => 'Nelson Mandela School',
                'funding_status'    => 'partially_funded',
            ],
            [
                'student_id'        => 'STD-2003',
                'student_name'      => 'Maryum Khan',
                'guardian_name'     => 'Tariq Khan',
                'guardian_phone'    => '+447555666777',
                'address'           => '78 Manchester Way, Manchester',
                'post_code'         => 'M1 1AE',
                'educational_level' => 'Higher',
                'institution_name'  => 'Manchester Academy',
                'funding_status'    => 'unfunded',
            ],
        ];

        foreach ($students as $student) {
            Student::updateOrCreate(['student_id' => $student['student_id']], $student);
        }
    }
}
