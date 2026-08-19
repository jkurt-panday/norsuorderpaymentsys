<?php

namespace App\Console\Commands;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillGraduateLedger extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'graduate:backfill';

    /**
     * The console command description.
     */
    protected $description = 'Backfill and normalize graduate ledger records from existing string columns to new tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Graduate Ledger normalization backfill...');

        // 1. Seed courses
        $this->info('Seeding courses...');
        foreach (Course::allCodes() as $code) {
            Course::firstOrCreate(['code' => $code]);
        }

        // 2. Fetch all records
        $records = GraduateLedger::all();
        $this->info("Processing {$records->count()} ledger records...");

        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        DB::transaction(function () use ($records, $bar) {
            foreach ($records as $record) {
                // Skip if already normalized
                if ($record->student_id) {
                    $bar->advance();

                    continue;
                }

                // Resolve Student
                $rawName = trim($record->student_name);
                if ($rawName !== '') {
                    $parsed = Student::parseRawName($rawName);
                    $student = Student::firstOrCreate(
                        ['raw_name_from_csv' => $rawName],
                        $parsed
                    );
                    $record->student_id = $student->id;
                }

                // Resolve Course
                $courseCode = trim($record->course);
                if ($courseCode !== '') {
                    $course = Course::firstOrCreate(['code' => $courseCode]);
                    $record->course_id = $course->id;
                }

                // Resolve Academic Term
                $sy = trim($record->school_year);
                $semShort = trim($record->semester_short);
                if ($sy !== '' && $semShort !== '') {
                    $term = AcademicTerm::firstOrCreate(
                        ['school_year' => $sy, 'semester_short' => $semShort],
                        [
                            'semester' => AcademicTerm::semesterLabel($semShort),
                            'sort_order' => AcademicTerm::sortOrder($semShort),
                        ]
                    );
                    $record->academic_term_id = $term->id;
                }

                // Resolve Entry Type
                $rawType = strtoupper(trim($record->ar_payment ?? ''));
                $rawAmount = (string) $record->amount;
                $isParenthesesNegative = str_contains($rawAmount, '(') && str_contains($rawAmount, ')');

                if ($rawType === 'AR') {
                    $record->entry_type = 'ar';
                } elseif ($rawType === 'ADJUSTMENT' || $rawType === 'ADJ') {
                    $record->entry_type = 'adjustment';
                } else {
                    $record->entry_type = 'payment';
                }

                $record->save();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Graduate Ledger normalization backfill completed successfully!');

        return 0;
    }
}
