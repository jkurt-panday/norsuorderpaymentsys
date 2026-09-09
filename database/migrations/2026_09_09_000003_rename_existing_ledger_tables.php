<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('law_school_ledgers', 'legacy_law_school_ledgers');
        Schema::rename('graduate_ledgers', 'legacy_graduate_ledgers');
    }

    public function down(): void
    {
        Schema::rename('legacy_graduate_ledgers', 'graduate_ledgers');
        Schema::rename('legacy_law_school_ledgers', 'law_school_ledgers');
    }
};
