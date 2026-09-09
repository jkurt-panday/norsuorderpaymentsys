<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop legacy columns from earlier migration attempts if they exist
        Schema::table('form_inputs', function (Blueprint $table) {
            if (Schema::hasColumn('form_inputs', 'course')) {
                $table->dropForeign(['course']);
                $table->dropColumn('course');
            }
            if (Schema::hasColumn('form_inputs', 'academic_term')) {
                $table->dropForeign(['academic_term']);
                $table->dropColumn('academic_term');
            }
            if (Schema::hasColumn('form_inputs', 'student_num')) {
                $table->dropColumn('student_num');
            }
        });

        // Add new columns with correct FK relationships
        Schema::table('form_inputs', function (Blueprint $table) {
            if (! Schema::hasColumn('form_inputs', 'student_num')) {
                $table->foreignId('student_num')->nullable()->after('payment_detail_option_id')->constrained('students')->nullOnDelete();
            }
            if (! Schema::hasColumn('form_inputs', 'academic_term')) {
                $table->foreignId('academic_term')->nullable()->after('student_num')->constrained('academic_terms')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('form_inputs', function (Blueprint $table) {
            $table->dropForeign(['academic_term']);
            $table->dropForeign(['student_num']);
            $table->dropColumn(['student_num', 'academic_term']);
        });
    }
};
