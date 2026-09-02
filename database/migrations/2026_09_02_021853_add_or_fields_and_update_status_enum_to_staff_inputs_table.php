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

        DB::statement("ALTER TABLE staff_inputs DROP CONSTRAINT IF EXISTS staff_inputs_status_check");
        DB::statement("ALTER TABLE staff_inputs ALTER COLUMN status DROP DEFAULT");
        DB::statement("ALTER TABLE staff_inputs ALTER COLUMN status TYPE VARCHAR(255)");
        DB::statement("ALTER TABLE staff_inputs ALTER COLUMN status SET DEFAULT 'pending'");
        DB::statement("ALTER TABLE staff_inputs ADD CONSTRAINT staff_inputs_status_check CHECK (status IN ('pending', 'processed', 'paid', 'approved', 'cancelled'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE staff_inputs DROP CONSTRAINT IF EXISTS staff_inputs_status_check");
        DB::statement("ALTER TABLE staff_inputs ALTER COLUMN status DROP DEFAULT");

        Schema::table('staff_inputs', function (Blueprint $table) {
            $table->dropColumn(['or_no', 'or_date']);
        });
    }
};
