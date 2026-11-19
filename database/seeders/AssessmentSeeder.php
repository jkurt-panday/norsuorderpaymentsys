<?php

namespace Database\Seeders;

use App\Models\AssessmentForm;
use App\Models\Courses;
use App\Services\ReferenceNumberService;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use RuntimeException;

class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $courseIds = Courses::query()
            ->publicAvailable()
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($courseIds === []) {
            throw new RuntimeException('Cannot seed assessments: no public courses exist. Run CourseSeeder first.');
        }

        $faker = FakerFactory::create();
        $referenceNumbers = app(ReferenceNumberService::class);
        $from = Carbon::create(2026, 11, 15)->startOfDay();
        $to = Carbon::create(2026, 11, 20)->endOfDay();

        for ($index = 0; $index < 20; $index++) {
            $submittedAt = Carbon::instance($faker->dateTimeBetween($from, $to));

            AssessmentForm::query()->create([
                'reference_number' => $referenceNumbers->assess_ref_gen(),
                'email' => sprintf('assessment-seed-%02d@example.test', $index + 1),
                'contact_num' => $faker->numerify('09#########'),
                'first_name' => $faker->firstName(),
                'middle_name' => $faker->optional()->firstName(),
                'last_name' => $faker->lastName(),
                'student_id' => $faker->numerify('202######'),
                'course_id' => $faker->randomElement($courseIds),
                'address' => $faker->address(),
                'enrolled_under' => $faker->randomElement([
                    'Graduate',
                    'School of Law',
                    'Undergraduate',
                ]),
                'sy_last_attended' => $faker->randomElement([
                    '2021-2022',
                    '2022-2023',
                    '2023-2024',
                    '2024-2025',
                    '2025-2026',
                ]),
                'semester' => $faker->randomElement([
                    'First Semester',
                    'Second Semester',
                    'Summer',
                ]),
                'status' => 'pending',
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ]);
        }

        $this->command?->info('Successfully seeded 20 pending assessments dated November 15–20, 2026.');
    }
}
