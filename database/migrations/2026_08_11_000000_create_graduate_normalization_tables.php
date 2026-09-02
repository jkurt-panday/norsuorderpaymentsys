<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create graduate_student table
        Schema::create('graduate_student', function (Blueprint $table) {
            $table->id();
            $table->string('student_number')->nullable()->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('raw_name_from_csv')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('raw_name_from_csv');
        });

        // 2. Create graduate_course table
        Schema::create('graduate_course', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        // 3. Create graduate_academic_term table
        Schema::create('graduate_academic_term', function (Blueprint $table) {
            $table->id();
            $table->string('school_year', 20);
            $table->string('semester_short', 20); 
            $table->string('semester', 50);
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();

            $table->unique(['school_year', 'semester_short']);
        });

        // 4. Add nullable foreign keys, entry_type & performance indexes to graduate_ledgers
        Schema::table('graduate_ledgers', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->after('id')->constrained('graduate_student')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->after('student_id')->constrained('graduate_course')->nullOnDelete();
            $table->foreignId('academic_term_id')->nullable()->after('course_id')->constrained('graduate_academic_term')->nullOnDelete();
            $table->enum('entry_type', ['ar', 'payment', 'adjustment'])->nullable()->after('particulars');

            $table->index('transaction_date');
            $table->index('reference_or_jev_number');
            $table->index('entry_type');
            $table->index(['student_id', 'entry_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('graduate_ledgers', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['course_id']);
            $table->dropForeign(['academic_term_id']);
            $table->dropColumn(['student_id', 'course_id', 'academic_term_id', 'entry_type']);
        });

        Schema::dropIfExists('graduate_academic_term');
        Schema::dropIfExists('graduate_course');
        Schema::dropIfExists('graduate_student');
    }
};
