<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop semester_short, normalize semester to 3 canonical values,
     * deduplicate terms, and enforce a new unique constraint.
     */
    public function up(): void
    {
        // 1. Normalize all existing semester values to 3 canonical strings
        $map = [
            'First Semester'  => ['1st', 'first'],
            'Second Semester' => ['2nd', 'second'],
            'Summer'          => ['summer', 'intersession'],
        ];

        $terms = DB::table('graduate_academic_term')->get();

        foreach ($terms as $term) {
            $lower    = strtolower(trim($term->semester));
            $canonical = 'First Semester'; // default

            if (str_contains($lower, '2nd') || str_contains($lower, 'second')) {
                $canonical = 'Second Semester';
            } elseif (str_contains($lower, 'summer') || str_contains($lower, 'intersession')) {
                $canonical = 'Summer';
            }

            if ($canonical !== $term->semester) {
                DB::table('graduate_academic_term')
                    ->where('id', $term->id)
                    ->update(['semester' => $canonical]);
            }
        }

        // 2. Deduplicate: for each (school_year, semester) group, keep the lowest id
        //    and re-point all graduate_ledgers referencing duplicate ids to the survivor.
        $groups = DB::table('graduate_academic_term')
            ->select('school_year', 'semester', DB::raw('MIN(id) as keep_id'))
            ->groupBy('school_year', 'semester')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $duplicateIds = DB::table('graduate_academic_term')
                ->where('school_year', $group->school_year)
                ->where('semester', $group->semester)
                ->where('id', '!=', $group->keep_id)
                ->pluck('id');

            // Re-point graduate_ledgers
            DB::table('graduate_ledgers')
                ->whereIn('academic_term_id', $duplicateIds)
                ->update(['academic_term_id' => $group->keep_id]);

            // Delete duplicate term rows
            DB::table('graduate_academic_term')
                ->whereIn('id', $duplicateIds)
                ->delete();
        }

        // 3. Update sort_order on all surviving rows
        $surviving = DB::table('graduate_academic_term')->get();
        foreach ($surviving as $term) {
            $sortOrder = match ($term->semester) {
                'Second Semester' => 2,
                'Summer'          => 3,
                default           => 1,
            };
            DB::table('graduate_academic_term')
                ->where('id', $term->id)
                ->update(['sort_order' => $sortOrder]);
        }

        // 4. Drop the old unique index on (school_year, semester_short)
        Schema::table('graduate_academic_term', function (Blueprint $table) {
            $table->dropUnique(['school_year', 'semester_short']);
        });

        // 5. Drop the semester_short column
        Schema::table('graduate_academic_term', function (Blueprint $table) {
            $table->dropColumn('semester_short');
        });

        // 6. Add new unique constraint on (school_year, semester)
        Schema::table('graduate_academic_term', function (Blueprint $table) {
            $table->unique(['school_year', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::table('graduate_academic_term', function (Blueprint $table) {
            $table->dropUnique(['school_year', 'semester']);
            $table->string('semester_short', 20)->nullable()->after('school_year');
        });
    }
};
