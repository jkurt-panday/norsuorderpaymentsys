<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = env('ADMIN_SEED_PASSWORD', Str::random(16));
        $staffPassword = env('STAFF_SEED_PASSWORD', Str::random(16));

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
                'name'     => 'Lead Staff',
                'password' => $staffPassword,
                'role'     => 'staff',
            ]
        );
        $this->command->info('Successfully seeded: Staff User (staff@norsu.edu.ph)');
        $this->command->warn('Seeded credentials must be rotated before production use.');
    }
}
