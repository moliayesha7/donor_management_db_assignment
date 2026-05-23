<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DemoEmailTrackerSeeder extends Seeder
{
    public function run(): void
    {
      
        $schedules = [
            [
                'id' => 1,
                'subject' => 'test',
                'deadline' => Carbon::parse('2024-11-10 08:41:00'),
                'status' => 'Completed',
                'started_at' => Carbon::parse('2024-11-10 08:41:00'),
                'completed_at' => Carbon::parse('2024-11-10 08:41:00'),
                'created_at' => Carbon::parse('2024-11-10 08:41:00'),
            ],
            [
                'id' => 2,
                'subject' => 'test again',
                'deadline' => Carbon::parse('2024-11-10 08:45:00'),
                'status' => 'Completed',
                'started_at' => Carbon::parse('2024-11-10 08:45:00'),
                'completed_at' => Carbon::parse('2024-11-10 08:46:00'),
                'created_at' => Carbon::parse('2024-11-10 08:45:00'),
            ],
            [
                'id' => 3,
                'subject' => 'TvOne',
                'deadline' => Carbon::parse('2023-12-15 20:20:00'),
                'status' => 'Completed',
                'started_at' => Carbon::parse('2023-12-15 20:20:00'),
                'completed_at' => Carbon::parse('2023-12-15 20:20:00'),
                'created_at' => Carbon::parse('2023-12-15 20:20:00'),
            ],
            [
                'id' => 4,
                'subject' => 'Direct Debit',
                'deadline' => Carbon::parse('2024-11-18 09:27:00'),
                'status' => 'Completed',
                'started_at' => Carbon::parse('2024-11-18 09:27:00'),
                'completed_at' => Carbon::parse('2024-11-18 09:27:00'),
                'created_at' => Carbon::parse('2024-11-18 09:27:00'),
            ]
        ];

        DB::table('email_schedules')->insert($schedules);

      
        $logs = [
            [
                'schedule_id' => 1,
                'subject' => 'test',
                'sent_by' => 'Super Admin',
                'recipient_name' => 'Arif Rahman',
                'recipient_email' => 'arif@example.com',
                'created_at' => Carbon::parse('2024-11-10 08:41:00'),
            ],
            [
                'schedule_id' => 2,
                'subject' => 'test again',
                'sent_by' => 'Super Admin',
                'recipient_name' => 'Ayesha Khatun',
                'recipient_email' => 'ayeshabdtask@gmail.comm',
                'created_at' => Carbon::parse('2024-11-10 08:45:00'),
            ],
            [
                'schedule_id' => 3,
                'subject' => 'TvOne',
                'sent_by' => 'Admin',
                'recipient_name' => 'Kamal Hossein',
                'recipient_email' => 'kamal@example.com',
                'created_at' => Carbon::parse('2023-12-15 20:20:00'),
            ],
            [
                'schedule_id' => 4,
                'subject' => 'Direct Debit',
                'sent_by' => 'Muiahidul Islam',
                'recipient_name' => 'John Doe',
                'recipient_email' => 'john.doe@ukmail.com',
                'created_at' => Carbon::parse('2024-11-18 09:27:00'),
            ]
        ];

        DB::table('email_logs')->insert($logs);
    }
}