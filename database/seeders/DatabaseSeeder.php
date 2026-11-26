<?php

namespace Database\Seeders;

use App\Models\BankAccountInfo;
use App\Models\Membership;
use App\Models\UACS;
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


        $this->command->warn(
            'Seeded credentials must be rotated before production use.'
        );

        // UACS seeding
        $uacsData = [
            ['norsu_object_code' => '5020', 'norsu_sub_object_code' => '01', 'uacs_object_code' => '50', 'uacs_sub_object_code' => '00', 'rca_code' => 'RCA-021', 'account_title' => 'Office Supplies'],
            ['norsu_object_code' => '5020', 'norsu_sub_object_code' => '02', 'uacs_object_code' => '50', 'uacs_sub_object_code' => '00', 'rca_code' => 'RCA-022', 'account_title' => 'Accountable Forms'],
            ['norsu_object_code' => '5020', 'norsu_sub_object_code' => '03', 'uacs_object_code' => '50', 'uacs_sub_object_code' => '00', 'rca_code' => 'RCA-023', 'account_title' => 'Non-Accountable Forms'],
            ['norsu_object_code' => '5029', 'norsu_sub_object_code' => '01', 'uacs_object_code' => '50', 'uacs_sub_object_code' => '90', 'rca_code' => 'RCA-024', 'account_title' => 'Other Supplies'],
        ];

        foreach ($uacsData as $uacs) {
            UACS::updateOrCreate(
                ['uacs_object_code' => $uacs['uacs_object_code'], 'uacs_sub_object_code' => $uacs['uacs_sub_object_code']],
                $uacs
            );
        }

        $this->command->info('Successfully seeded: UACS');

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

        // student seeder
        $this->call([
            StudentSeeder::class,
            AcademicTermSeeder::class,
            CourseSeeder::class,
            AssessmentSeeder::class,
            TestGraduateStudentsSeeder::class,
        ]);

        $this->call(GraduateLedgerSeeder::class, false, [
            'studentLimit' => 50,
            'termsPerStudent' => 6,
            'transactionsPerTerm' => 12,
        ]);

        $this->call(LawSchoolLedgerSeeder::class, false, [
            'studentLimit' => 50,
            'termsPerStudent' => 6,
            'transactionsPerTerm' => 10,
        ]);
    }
}
