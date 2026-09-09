<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement('CREATE INDEX IF NOT EXISTS law_school_ledgers_first_name_trgm_idx ON law_school_ledgers USING gin (LOWER(first_name) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS law_school_ledgers_last_name_trgm_idx ON law_school_ledgers USING gin (LOWER(last_name) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS law_school_ledgers_middle_initial_trgm_idx ON law_school_ledgers USING gin (LOWER(middle_initial) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS law_school_ledgers_reference_trgm_idx ON law_school_ledgers USING gin (LOWER(reference_jev_or_number) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS law_school_ledgers_particulars_trgm_idx ON law_school_ledgers USING gin (LOWER(particulars) gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS law_school_ledgers_first_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS law_school_ledgers_last_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS law_school_ledgers_middle_initial_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS law_school_ledgers_reference_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS law_school_ledgers_particulars_trgm_idx');
    }
};
