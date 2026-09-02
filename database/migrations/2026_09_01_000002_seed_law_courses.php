<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\LawCourse;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $courses = [
            ['code' => 'JD', 'title' => 'Juris Doctor'],
            ['code' => 'LLM', 'title' => 'Master of Laws'],
            ['code' => 'JSD', 'title' => 'Doctor of Juridical Science'],
        ];

        foreach ($courses as $course) {
            LawCourse::firstOrCreate(
                ['code' => $course['code']],
                ['title' => $course['title']]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        LawCourse::whereIn('code', ['JD', 'LLM', 'JSD'])->delete();
    }
};
