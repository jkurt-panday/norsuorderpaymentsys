<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_inputs', function (Blueprint $table) {
            $table->string('purpose', 300)->nullable()->after('ref_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('staff_inputs', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};