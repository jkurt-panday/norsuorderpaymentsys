<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_inputs', function (Blueprint $table) {
            $table->string('submitted_student_number', 50)
                ->nullable()
                ->after('student_num');
        });
    }

    public function down(): void
    {
        Schema::table('form_inputs', function (Blueprint $table) {
            $table->dropColumn('submitted_student_number');
        });
    }
};
