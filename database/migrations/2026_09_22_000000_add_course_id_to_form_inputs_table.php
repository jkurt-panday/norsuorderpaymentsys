<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_inputs', function (Blueprint $table) {
            $table->foreignId('course_id')
                ->nullable()
                ->after('student_num')
                ->constrained('courses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_inputs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_id');
        });
    }
};
