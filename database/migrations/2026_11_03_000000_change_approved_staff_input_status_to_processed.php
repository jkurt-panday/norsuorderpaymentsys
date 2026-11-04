<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename the persisted staff input status from approved to processed.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE staff_inputs DROP CONSTRAINT IF EXISTS staff_inputs_status_check');
            DB::statement("ALTER TABLE staff_inputs ADD CONSTRAINT staff_inputs_status_check CHECK (status IN ('pending', 'approved', 'processed', 'paid', 'cancelled'))");
        } else {
            Schema::table('staff_inputs', function (Blueprint $table): void {
                $table->enum('status', ['pending', 'approved', 'processed', 'paid', 'cancelled'])->default('pending')->change();
            });
        }

        DB::table('staff_inputs')
            ->where('status', 'approved')
            ->update(['status' => 'processed']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE staff_inputs DROP CONSTRAINT IF EXISTS staff_inputs_status_check');
            DB::statement("ALTER TABLE staff_inputs ADD CONSTRAINT staff_inputs_status_check CHECK (status IN ('pending', 'processed', 'paid', 'cancelled'))");
        } else {
            Schema::table('staff_inputs', function (Blueprint $table): void {
                $table->enum('status', ['pending', 'processed', 'paid', 'cancelled'])->default('pending')->change();
            });
        }
    }

    /**
     * Restore the approved status when rolling back this migration.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE staff_inputs DROP CONSTRAINT IF EXISTS staff_inputs_status_check');
            DB::statement("ALTER TABLE staff_inputs ADD CONSTRAINT staff_inputs_status_check CHECK (status IN ('pending', 'processed', 'approved', 'paid', 'cancelled'))");
        } else {
            Schema::table('staff_inputs', function (Blueprint $table): void {
                $table->enum('status', ['pending', 'processed', 'approved', 'paid', 'cancelled'])->default('pending')->change();
            });
        }

        DB::table('staff_inputs')
            ->where('status', 'processed')
            ->update(['status' => 'approved']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE staff_inputs DROP CONSTRAINT IF EXISTS staff_inputs_status_check');
            DB::statement("ALTER TABLE staff_inputs ADD CONSTRAINT staff_inputs_status_check CHECK (status IN ('pending', 'approved', 'paid', 'cancelled'))");
        } else {
            Schema::table('staff_inputs', function (Blueprint $table): void {
                $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending')->change();
            });
        }
    }
};
