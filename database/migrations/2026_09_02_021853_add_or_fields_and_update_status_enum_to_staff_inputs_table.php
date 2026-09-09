<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff_inputs', function (Blueprint $table) {
            $table->string('or_no')->nullable()->after('purpose');
            $table->date('or_date')->nullable()->after('or_no');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table('staff_inputs', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });

            return;
        }

        DB::statement("ALTER TABLE staff_inputs DROP CONSTRAINT IF EXISTS staff_inputs_status_check");
        DB::statement("ALTER TABLE staff_inputs ALTER COLUMN status DROP DEFAULT");
        DB::statement("ALTER TABLE staff_inputs ALTER COLUMN status TYPE VARCHAR(255)");
        DB::statement("ALTER TABLE staff_inputs ALTER COLUMN status SET DEFAULT 'pending'");
        DB::statement("ALTER TABLE staff_inputs ADD CONSTRAINT staff_inputs_status_check CHECK (status IN ('pending', 'processed', 'paid', 'cancelled'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE staff_inputs DROP CONSTRAINT IF EXISTS staff_inputs_status_check");
            DB::statement("ALTER TABLE staff_inputs ALTER COLUMN status DROP DEFAULT");
        } else {
            Schema::table('staff_inputs', function (Blueprint $table) {
                $table->enum('status', ['pending', 'approved', 'cancelled'])->default('pending')->change();
            });
        }

        Schema::table('staff_inputs', function (Blueprint $table) {
            $table->dropColumn(['or_no', 'or_date']);
        });
    }
};
