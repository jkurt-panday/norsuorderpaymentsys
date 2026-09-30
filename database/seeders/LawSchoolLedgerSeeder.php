<?php

namespace Database\Seeders;

use App\Models\Courses;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use App\Models\AcademicTerm;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class LawSchoolLedgerSeeder extends Seeder
{
    /**
     * Allowed values for constrained columns.
     */
    private const ENTRY_TYPES = ['ar', 'adjustment', 'payment'];

    private const REMARKS = ['Outstanding', 'Settled'];

    private const STATUSES = [
        'DROP',
        'OVERPAYMENT',
        '100% Tuition (MCL)',
        '100% Tuition (SCL)',
        '50% Tuition (CL)',
    ];

    private const PARTICULARS = ['Tuition', 'Other School Fees'];

    /**
     * Run the database seeds.
     *
     * Students already used by GraduateLedgerSeeder are excluded, so no
     * student ends up with ledger activity in both colleges unless you
     * disable $excludeGraduateStudents.
     *
     * @param int  $studentLimit             How many students to generate ledger activity for.
     * @param int  $termsPerStudent          Max number of academic terms each student has activity in.
     * @param int  $transactionsPerTerm      How many ledger rows per term.
     * @param bool $excludeGraduateStudents  Skip students already present in graduate_ledgers.
     *
     * Examples:
     *   php artisan tinker
     *   >>> (new Database\Seeders\LawSchoolLedgerSeeder)->run(60, 3, 5);
     *
     *   Or via DatabaseSeeder:
     *   $this->call(LawSchoolLedgerSeeder::class, false, [
     *       'studentLimit' => 60,
     *       'termsPerStudent' => 3,
     *       'transactionsPerTerm' => 5,
     *   ]);
     */
    public function run(
        int $studentLimit = 25,
        int $termsPerStudent = 3,
        int $transactionsPerTerm = 4,
        bool $excludeGraduateStudents = true
    ): void {
        $studentQuery = Student::query();

        if ($excludeGraduateStudents) {
            $excludedIds = GraduateLedger::query()->distinct()->pluck('student_id');
            $studentQuery->whereNotIn('id', $excludedIds);
        }

        $students = $studentQuery->inRandomOrder()->limit($studentLimit)->get();
        $terms = AcademicTerm::query()->orderBy('school_year')->orderBy('semester')->get();
        $course = Courses::query()->where('course_college', 'School of Law')->first();

        // Only staff or admin users are allowed to be attributed as the
        // person who entered the ledger transaction.
        $inputByIds = User::query()
            ->whereIn('role', ['staff', 'admin'])
            ->pluck('id');

        if ($students->isEmpty() || $terms->isEmpty() || ! $course) {
            $this->command?->warn(
                'Skipping LawSchoolLedgerSeeder: run StudentSeeder, AcademicTermSeeder, and CourseSeeder first, '.
                'or all eligible students are already used by GraduateLedgerSeeder.'
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
                for ($i = 0; $i < $transactionsPerTerm; $i++) {
                    $entryType = fake()->randomElement(self::ENTRY_TYPES);
                    $particulars = fake()->randomElement(self::PARTICULARS);
                    $units = fake()->numberBetween(3, 17);
                    $rate = fake()->randomElement([900, 1000, 1100]);

                    $amount = match ($entryType) {
                        'ar' => $units * $rate,
                        'adjustment' => fake()->randomFloat(2, -2000, 2000),
                        'payment' => fake()->randomFloat(2, 500, 3000),
                    };

                    $referencePrefix = match ($entryType) {
                        'ar' => 'OP-',
                        'adjustment' => 'ADJ-',
                        'payment' => 'OR-',
                    };

                    LawSchoolLedger::query()->create([
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
                        'status' => fake()->randomElement(self::STATUSES),
                        'input_by' => $inputByIds->isNotEmpty() ? $inputByIds->random() : null,
                        'imported_input_by' => null,
                        'latin_honor' => null,
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