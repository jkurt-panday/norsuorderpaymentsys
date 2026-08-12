<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\BankAccountInfo;
use App\Models\UACS;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = env('ADMIN_SEED_PASSWORD', Str::random(16));
        $accountantPassword = env('ACCOUNTANT_SEED_PASSWORD', Str::random(16));

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

        // 4. Create the System Accountant
        User::updateOrCreate(
            ['email' => 'accountant@norsu.edu.ph'],
            [
                'name' => 'Lead Accountant',
                'password' => $accountantPassword,
                'role' => 'accountant',
            ]
        );
        $this->command->info('Successfully seeded: Accountant User (accountant@norsu.edu.ph)');
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
                ['fund_cluster' => $option['fund_cluster']],
                ['bank_name' => $option['bank_name']],
                ['account_num' => $option['account_num']],
            );
        }

        $this->command->info(
            'Successfully seeded: Bank Account Info'
        );

        // $uacs = [
        //     [
        //         'account_name' => 'NORSU Special Trust Fund 164 - Miscellaneous',
        //         'fund_cluster' => 'M-164',
        //         'bank_name' => 'DBP Dumaguete',
        //         'account_num' => '0740-012653-080',
        //     ],
        //     [
        //         'account_name' => 'NORSU Special Trust Fund 164 - Tuition',
        //         'fund_cluster' => 'T-164',
        //         'bank_name' => 'DBP Dumaguete',
        //         'account_num' => '0740-012637-032',
        //     ],
        // ];
       
        // foreach ($bankaccount as $option) {
        //     BankAccountInfo::updateOrCreate(
        //         ['account_name' => $option['account_name']],
        //         ['func_cluster' => $option['func_cluster']],
        //         ['bank_name' => $option['bank_name']],
        //         ['account_num' => $option['account_num']],
        //     );
        // }

        // $this->command->info(
        //     'Successfully seeded: Bank Account Info'
        // );
    }
}
