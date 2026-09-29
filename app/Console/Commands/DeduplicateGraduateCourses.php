<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\GraduateLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateGraduateCourses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledger:deduplicate-graduate-courses {--dry-run : Only show what would be merged without changing data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merges duplicate graduate courses (e.g. "MS. Math" and "M.S. Math" into "MA Mathematics") and updates ledger foreign keys.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode. No changes will be saved to the database.');
        }

        $graduateCourses = Course::where('course_college', 'Graduate School')->get();

        if ($graduateCourses->isEmpty()) {
            $this->info('No Graduate School courses found.');

            return self::SUCCESS;
        }

        // Group courses by canonical code
        $canonicalMap = [];
        $unresolvable = [];

        foreach ($graduateCourses as $course) {
            $canonical = Course::resolveCanonicalCode($course->course_code);

            if ($canonical === null) {
                // If it can't resolve to a known program, normalize its code as the bucket key
                $canonical = Course::normalizeCode($course->course_code);
            }

            $canonicalMap[$canonical][] = $course;
        }

        $mergedCount = 0;
        $updatedLedgersCount = 0;

        DB::beginTransaction();

        try {
            foreach ($canonicalMap as $canonicalCode => $courses) {
                // Determine expected course_desc from master registry
                $expectedDesc = Course::descriptionFor($canonicalCode);

                if (count($courses) <= 1) {
                    $single = $courses[0];
                    if (! $dryRun) {
                        $updates = [];
                        if ($single->course_code !== $canonicalCode) {
                            $updates['course_code'] = $canonicalCode;
                        }
                        if ($expectedDesc !== null && $single->course_desc !== $expectedDesc) {
                            $updates['course_desc'] = $expectedDesc;
                        }
                        if (! empty($updates)) {
                            $single->update($updates);
                        }
                    }

                    continue;
                }

                // Pick the master course row: prefer exact canonical match, otherwise lowest ID
                $masterCourse = null;
                foreach ($courses as $c) {
                    if ($c->course_code === $canonicalCode) {
                        $masterCourse = $c;
                        break;
                    }
                }

                if ($masterCourse === null) {
                    $masterCourse = $courses[0];
                }

                if (! $dryRun) {
                    $masterUpdates = [];
                    if ($masterCourse->course_code !== $canonicalCode) {
                        $masterUpdates['course_code'] = $canonicalCode;
                    }
                    if ($expectedDesc !== null && $masterCourse->course_desc !== $expectedDesc) {
                        $masterUpdates['course_desc'] = $expectedDesc;
                    }
                    if (! empty($masterUpdates)) {
                        $masterCourse->update($masterUpdates);
                    }
                }

                $duplicates = array_filter($courses, fn (Course $c): bool => $c->id !== $masterCourse->id);
                $dupIds = array_map(fn (Course $c): int => (int) $c->id, $duplicates);

                $this->info('Merging ['.implode(', ', array_map(fn (Course $c) => $c->course_code, $duplicates))."] into '{$canonicalCode}' (ID: {$masterCourse->id})");

                $affectedLedgers = GraduateLedger::whereIn('course_id', $dupIds)->count();

                if (! $dryRun) {
                    if ($affectedLedgers > 0) {
                        GraduateLedger::whereIn('course_id', $dupIds)->update(['course_id' => $masterCourse->id]);
                    }
                    Course::whereIn('id', $dupIds)->delete();
                }

                $mergedCount += count($duplicates);
                $updatedLedgersCount += $affectedLedgers;
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info("Dry run complete: {$mergedCount} duplicate course(s) found, {$updatedLedgersCount} ledger record(s) would be updated.");
            } else {
                DB::commit();
                $this->info("Deduplication complete: {$mergedCount} duplicate course(s) merged, {$updatedLedgersCount} ledger record(s) updated.");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Deduplication failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
