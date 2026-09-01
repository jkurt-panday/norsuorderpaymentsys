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
        // 1. Create law_student table
        Schema::create('law_student', function (Blueprint $table) {
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

        // 2. Create law_course table
        Schema::create('law_course', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        // 3. Create law_academic_term table
        Schema::create('law_academic_term', function (Blueprint $table) {
            $table->id();
            $table->string('school_year', 20);
            $table->string('semester_short', 20);
            $table->string('semester', 50);
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();

            $table->unique(['school_year', 'semester_short']);
        });

        // 4. Add nullable foreign keys and entry_type to law_school_ledgers
        Schema::table('law_school_ledgers', function (Blueprint $table) {
            // student_id already exists as string column - skip if exists
            if (! Schema::hasColumn('law_school_ledgers', 'student_id_fk')) {
                $table->foreignId('student_id_fk')->nullable()->after('id')->constrained('law_student')->cascadeOnDelete();
            }
            $table->foreignId('course_id')->nullable()->after('id')->constrained('law_course')->nullOnDelete();
            $table->foreignId('academic_term_id')->nullable()->after('course_id')->constrained('law_academic_term')->nullOnDelete();
            $table->enum('entry_type', ['ar', 'payment', 'adjustment'])->nullable()->after('particulars');

            $table->index('transaction_date');
            $table->index('reference_jev_or_number');
            $table->index('entry_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('law_school_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('law_school_ledgers', 'student_id_fk')) {
                $table->dropForeign(['student_id_fk']);
                $table->dropColumn('student_id_fk');
            }
            $table->dropForeign(['course_id']);
            $table->dropForeign(['academic_term_id']);
            $table->dropColumn(['course_id', 'academic_term_id', 'entry_type']);
        });

        Schema::dropIfExists('law_academic_term');
        Schema::dropIfExists('law_course');
        Schema::dropIfExists('law_student');
    }
};
