<?php

namespace Database\Seeders;

use App\Models\AssessmentForm;
use App\Models\Courses;
use App\Services\ReferenceNumberService;
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
        $seedCount = 20;
        $seedStart = now()->startOfMinute();
        $submittedAt = $seedStart->copy();

        // Keep the generated records chronologically ordered while spreading
        // them across today and future dates. Each record advances by a random
        // one-to-three-day interval (for example: today, tomorrow, then three
        // days later, then another day later). References are generated in the
        // same loop order, keeping them aligned with created_at ordering.
        for ($index = 0; $index < $seedCount; $index++) {
            if ($index > 0) {
                $submittedAt = $submittedAt->copy()->addDays(random_int(1, 3));
            }

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

        $this->command?->info(sprintf(
            'Successfully seeded %d pending assessments starting %s.',
            $seedCount,
            $seedStart->toDateTimeString(),
        ));
    }
}
