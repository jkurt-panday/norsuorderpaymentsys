<?php

namespace Database\Seeders;

use App\Models\Courses;
use App\Models\GraduateLedger;
use App\Models\Student;
use App\Models\AcademicTerm;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class GraduateLedgerSeeder extends Seeder
{
    /**
     * Allowed values for constrained columns.
     */
    private const ENTRY_TYPES = ['AR', 'ADJUSTMENT', 'PAYMENT'];

    private const REMARKS = ['Outstanding', 'Settled'];

    private const PARTICULARS = ['Tuition', 'Registration', 'Miscellaneous'];

    /**
     * Run the database seeds.
     *
     * @param int $studentLimit        How many students to generate ledger activity for.
     * @param int $termsPerStudent     Max number of academic terms each student has activity in.
     * @param int $transactionsPerTerm How many ledger rows per term.
     *
     * Examples:
     *   php artisan tinker
     *   >>> (new Database\Seeders\GraduateLedgerSeeder)->run(100, 3, 6);
     *
     *   Or via DatabaseSeeder:
     *   $this->call(GraduateLedgerSeeder::class, false, [
     *       'studentLimit' => 100,
     *       'termsPerStudent' => 3,
     *       'transactionsPerTerm' => 6,
     *   ]);
     */
    public function run(int $studentLimit = 40, int $termsPerStudent = 2, int $transactionsPerTerm = 4): void
    {
        $students = Student::query()->inRandomOrder()->limit($studentLimit)->get();
        $terms = AcademicTerm::query()->orderBy('school_year')->orderBy('semester')->get();
        $courses = Courses::query()->where('course_college', 'Graduate School')->get();

        // Only staff or admin users are allowed to be attributed as the
        // person who entered the ledger transaction.
        $inputByIds = User::query()
            ->whereIn('role', ['staff', 'admin'])
            ->pluck('id');

        if ($students->isEmpty() || $terms->isEmpty() || $courses->isEmpty()) {
            $this->command?->warn(
                'Skipping GraduateLedgerSeeder: run StudentSeeder, AcademicTermSeeder, and CourseSeeder first.'
            );

            return;
        }

        if ($inputByIds->isEmpty()) {
            $this->command?->warn(
                'No users with role "staff" or "admin" found — input_by will be left null for all rows.'
            );
        }

        foreach ($students as $student) {
            $studentTerms = $this->pickRandom($terms, $termsPerStudent);

            foreach ($studentTerms as $term) {
                $course = $courses->random();

                for ($i = 0; $i < $transactionsPerTerm; $i++) {
                    $entryType = fake()->randomElement(self::ENTRY_TYPES);
                    $particulars = fake()->randomElement(self::PARTICULARS);
                    $units = fake()->numberBetween(3, 30);
                    $rate = fake()->randomElement([1200, 1500, 1800, 2000]);

                    $amount = match ($entryType) {
                        'AR' => $units * $rate,
                        'ADJUSTMENT' => fake()->randomFloat(2, -2000, 2000),
                        'PAYMENT' => fake()->randomFloat(2, 500, 3000),
                    };

                    $referencePrefix = match ($entryType) {
                        'AR' => 'OP-',
                        'ADJUSTMENT' => 'ADJ-',
                        'PAYMENT' => 'OR-',
                    };

                    GraduateLedger::query()->create([
                        'student_id' => $student->id,
                        'academic_term_id' => $term->id,
                        'course_id' => $course->id,
                        'units' => $units,
                        'rate' => $rate,
                        'entry_type' => $entryType,
                        'amount' => $amount,
                        'transaction_date' => fake()->dateTimeBetween('-2 years', 'now'),
                        'reference_number' => $referencePrefix.fake()->unique()->numerify('######'),
                        'particulars' => $particulars,
                        'remarks' => fake()->randomElement(self::REMARKS),
                        'status' => 'posted',
                        'input_by' => $inputByIds->isNotEmpty() ? $inputByIds->random() : null,
                        'imported_input_by' => null,
                        'membership' => null,
                        'discount_amount' => 0,
                    ]);
                }
            }
        }
    }

    /**
     * Pick up to $count random items from a collection without
     * throwing when the collection is smaller than $count.
     */
    private function pickRandom(Collection $items, int $count): Collection
    {
        $count = max(1, min($count, $items->count()));
        $result = $items->random($count);

        return $result instanceof Collection ? $result : collect([$result]);
    }
}