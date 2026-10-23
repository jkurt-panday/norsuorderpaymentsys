<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE staff_inputs DROP CONSTRAINT IF EXISTS staff_inputs_status_check');
            DB::statement("ALTER TABLE staff_inputs ADD CONSTRAINT staff_inputs_status_check CHECK (status IN ('pending', 'processed', 'paid', 'cancelled'))");
        } else {
            Schema::table('staff_inputs', function (Blueprint $table): void {
                $table->enum('status', ['pending', 'processed', 'paid', 'cancelled'])->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE staff_inputs DROP CONSTRAINT IF EXISTS staff_inputs_status_check');
            DB::statement("ALTER TABLE staff_inputs ADD CONSTRAINT staff_inputs_status_check CHECK (status IN ('pending', 'processed', 'paid', 'cancelled'))");
        } else {
            Schema::table('staff_inputs', function (Blueprint $table): void {
                $table->enum('status', ['pending', 'processed', 'paid', 'cancelled'])->default('pending')->change();
            });
        }
    }
};
