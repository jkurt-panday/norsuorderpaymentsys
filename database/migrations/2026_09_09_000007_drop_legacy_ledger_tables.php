<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('legacy_law_school_ledgers');
        Schema::dropIfExists('legacy_graduate_ledgers');

        Schema::dropIfExists('law_academic_term');
        Schema::dropIfExists('law_course');
        Schema::dropIfExists('law_student');

        Schema::dropIfExists('graduate_academic_term');
        Schema::dropIfExists('graduate_course');
        Schema::dropIfExists('graduate_student');
    }

    public function down(): void
    {
        // Intentionally irreversible. Restoring the table structures would not
        // restore their deleted ledger data; use a database backup instead.
    }
};
