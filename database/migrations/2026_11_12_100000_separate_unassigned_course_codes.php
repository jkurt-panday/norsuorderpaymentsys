<?php

use App\Models\Course;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyCourse = DB::table('courses')->where('course_code', 'UNASSIGNED')->first();

        if ($legacyCourse === null) {
            return;
        }

        $isLawCourse = $legacyCourse->course_college === 'School of Law';
        $targetCode = $isLawCourse ? Course::LAW_FALLBACK_CODE : Course::GRADUATE_FALLBACK_CODE;
        $existingTarget = DB::table('courses')->where('course_code', $targetCode)->first();

        if ($existingTarget !== null) {
            foreach (['graduate_ledgers', 'law_school_ledgers', 'form_inputs', 'assessment_forms'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'course_id')) {
                    DB::table($table)
                        ->where('course_id', $legacyCourse->id)
                        ->update(['course_id' => $existingTarget->id]);
                }
            }

            DB::table('courses')->where('id', $legacyCourse->id)->delete();

            return;
        }

        DB::table('courses')->where('id', $legacyCourse->id)->update([
            'course_code' => $targetCode,
            'course_desc' => $isLawCourse
                ? 'Unassigned / Pending Law Course Assignment'
                : 'Unassigned / Pending Graduate Course Assignment',
        ]);
    }

    public function down(): void
    {
        if (DB::table('courses')->where('course_code', 'UNASSIGNED')->exists()) {
            return;
        }

        $fallbackQuery = DB::table('courses')
            ->whereIn('course_code', [Course::GRADUATE_FALLBACK_CODE, Course::LAW_FALLBACK_CODE]);

        if ((clone $fallbackQuery)->count() === 1 && ($fallback = $fallbackQuery->first()) !== null) {
            DB::table('courses')->where('id', $fallback->id)->update([
                'course_code' => 'UNASSIGNED',
                'course_desc' => 'Unassigned / Pending Course Assignment',
            ]);
        }
    }
};
