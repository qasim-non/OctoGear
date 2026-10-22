<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use Database\Seeders\Support\SeedRecords;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RequestedAdminsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ([
                'abdulrahman_octogear@gmail.com' => 'Abdulrahman',
                'qasim00programmer@gmail.com' => 'Qasim',
            ] as $email => $name) {
                // Preserve existing and archived accounts, including completed setup.
                if (DB::table('admin')->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    continue;
                }

                SeedRecords::once('admin', ['email' => $email], [
                    'name' => $name,
                    'mobile' => '',
                    'password' => Hash::make('44448888'),
                    'assigned_role' => AdminRole::Admin->value,
                    'status' => AdminStatus::Active->value,
                    'must_change_password' => true,
                    'temporary_password_expires_at' => now()->addDay(),
                    'security_version' => 1,
                ], 'employee_id');
            }
        });
    }
}
