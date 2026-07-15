<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
                'password' => Hash::make($adminPassword),
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
    }
}
