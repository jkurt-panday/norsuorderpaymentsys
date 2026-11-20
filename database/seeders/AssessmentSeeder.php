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
        $requestsPerDay = 5;
        $seedCount = 100;
        $seedDays = intdiv($seedCount, $requestsPerDay);
        // Spread exactly 100 requests across 20 complete days, ending
        // yesterday. This gives an average of five requests per day while
        // keeping the generated timestamps chronological.
        $seedStart = now()->startOfDay()->subDays($seedDays);

        // Reference numbers are generated in this same chronological loop.
        // Therefore, within this seeded batch, lower reference numbers always
        // represent older submissions.
        for ($index = 0; $index < $seedCount; $index++) {
            $dayOffset = intdiv($index, $requestsPerDay);
            $positionInDay = $index % $requestsPerDay;
            $minutesPerSlot = intdiv(1_440, $requestsPerDay);
            $slotStart = $positionInDay * $minutesPerSlot;
            $submittedAt = $seedStart->copy()
                ->addDays($dayOffset)
                ->addMinutes($slotStart + random_int(0, $minutesPerSlot - 1));

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
            'Successfully seeded %d pending assessments across %d days, starting %s.',
            $seedCount,
            $seedDays,
            $seedStart->toDateTimeString(),
        ));
    }
}
