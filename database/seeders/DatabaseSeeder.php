<?php

namespace Database\Seeders;

use App\Models\BankAccountInfo;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\User;
use App\Models\YearSequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $configuredAdminPassword = config('seeding.admin_password');
        $configuredStaffPassword = config('seeding.staff_password');
        $adminPassword = is_string($configuredAdminPassword) && $configuredAdminPassword !== ''
            ? $configuredAdminPassword
            : Str::random(16);
        $staffPassword = is_string($configuredStaffPassword) && $configuredStaffPassword !== ''
            ? $configuredStaffPassword
            : Str::random(16);

        // 3. Create the System Administrator
        User::updateOrCreate(
            ['email' => 'admin@norsu.edu.ph'],
            [
                'name' => 'System Admin',
                'password' => $adminPassword,
                'role' => 'admin',
            ]
        );
        $this->command->info('Successfully seeded: Admin User (admin@norsu.edu.ph)');

        // 4. Create the System Staff
        User::updateOrCreate(
            ['email' => 'staff@norsu.edu.ph'],
            [
                'name' => 'Lead Staff',
                'password' => $staffPassword,
                'role' => 'staff',
            ]
        );
        $this->command->info('Successfully seeded: Staff User (staff@norsu.edu.ph)');
        $this->command->warn('Seeded credentials must be rotated before production use.');

        $memberships = [
            [
                'member_code' => 'NAPU',
                'member_desc' => 'NORSU Administrative Personnel Union',
            ],
            [
                'member_code' => 'NORSUFFA',
                'member_desc' => 'Negros Oriental State University Federated Faculty Association',
            ],
            [
                'member_code' => 'N/A',
                'member_desc' => 'Not Applicable',
            ],
        ];

        foreach ($memberships as $membership) {
            Membership::updateOrCreate(
                ['member_code' => $membership['member_code']],
                ['member_desc' => $membership['member_desc']]
            );
        }

        $this->command->info('Successfully seeded: Memberships');

        /*
        |--------------------------------------------------------------------------
        | Payment Detail Options
        |--------------------------------------------------------------------------
        */

        $paymentOptions = [
            [
                'payment_desc' => 'Comprehensive Exam - Doctorate',
            ],
            [
                'payment_desc' => 'Comprehensive Exam - Masters',
            ],
            [
                'payment_desc' => 'IGP FABLAB',
            ],
        ];

        foreach ($paymentOptions as $option) {
            PaymentDetailOption::updateOrCreate(
                ['payment_desc' => $option['payment_desc']],
                $option
            );
        }

        $this->command->info(
            'Successfully seeded: Payment Detail Options'
        );

        $this->command->warn(
            'Seeded credentials must be rotated before production use.'
        );

        // $table->string('account_name', 255);
        // $table->string('fund_cluster', 50);
        // $table->string('bank_name', 100);
        // $table->string('account_num', 50)->unique();

        $bankaccount = [
            [
                'account_name' => 'NORSU Special Trust Fund 164 - Miscellaneous',
                'fund_cluster' => 'M-164',
                'bank_name' => 'DBP Dumaguete',
                'account_num' => '0740-012653-080',
            ],
            [
                'account_name' => 'NORSU Special Trust Fund 164 - Tuition',
                'fund_cluster' => 'T-164',
                'bank_name' => 'DBP Dumaguete',
                'account_num' => '0740-012637-032',
            ],
        ];

        foreach ($bankaccount as $option) {
            BankAccountInfo::updateOrCreate(
                ['account_name' => $option['account_name']],
                $option
            );
        }

        $this->command->info(
            'Successfully seeded: Bank Account Info'
        );

        // $courses = [
        //     [
        //         'course_code' => 'BSCS',
        //         'course_desc' => 'BoS in Computer Science',
        //     ],
        //     [
        //         'course_code' => 'BSLaw',
        //         'course_desc' => 'BoS in Law Studies',
        //     ],
        // ];

        // foreach ($courses as $option) {
        //     Courses::updateOrCreate(
        //         ['course_code' => $option['course_code']],
        //         ['course_desc' => $option['course_desc']],
        //     );
        // }

        $this->command->info(
            'Successfully seeded: Courses'
        );

        YearSequence::updateOrCreate(
            ['year' => 2026],
            [
                'month' => 8,
                'op_number' => 4224,
                'assessment_number' => 0,
            ]
        );

        $this->command->info('Successfully seede: YearSequence.');
    }
}
