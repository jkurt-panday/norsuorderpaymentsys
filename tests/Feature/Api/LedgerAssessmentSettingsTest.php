<?php

namespace Tests\Feature\Api;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LedgerAssessmentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $student = Student::create(['student_number' => '2026001', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com']);
        $term = AcademicTerm::create(['school_year' => '2026-2027', 'semester' => 'First Semester']);
        $graduate = Course::create(['course_code' => 'MS', 'course_desc' => 'Master', 'course_college' => 'Graduate School']);
        $law = Course::create(['course_code' => 'JD', 'course_desc' => 'Juris Doctor', 'course_college' => 'School of Law']);

        return compact('student', 'term', 'graduate', 'law');
    }

    public function test_staff_can_create_list_and_view_balances_for_both_ledgers(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $f = $this->fixtures();
        foreach ([['graduate-ledger', $f['graduate']], ['law-ledger', $f['law']]] as [$endpoint, $course]) {
            $response = $this->actingAs($staff)->postJson('/api/v1/'.$endpoint, ['student_id' => $f['student']->id, 'course_id' => $course->id, 'academic_term_id' => $f['term']->id, 'entry_type' => 'ar', 'transaction_date' => '2026-11-13', 'particulars' => 'Assessment', 'rate' => 100, 'amount' => 100, 'status' => 'posted'])->assertCreated();
            $this->actingAs($staff)->getJson('/api/v1/'.$endpoint)->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
            $ledger = $endpoint === 'graduate-ledger' ? GraduateLedger::first() : LawSchoolLedger::first();
            $this->actingAs($staff)->getJson('/api/v1/'.$endpoint.'/students/'.$f['student']->id.'/balance')->assertOk()->assertJsonPath('student.id', $f['student']->id);
            $this->actingAs($staff)->deleteJson('/api/v1/'.$endpoint.'/'.$ledger->id)->assertNoContent();
        }
    }

    public function test_staff_can_update_profile_and_password(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => Hash::make('OldPassword123!')]);
        $this->actingAs($staff)->patchJson('/api/v1/settings/profile', ['name' => 'Updated Name', 'email' => 'updated@example.com'])->assertOk();
        $this->actingAs($staff)->putJson('/api/v1/settings/password', ['current_password' => 'OldPassword123!', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertOk();
    }

    public function test_non_staff_cannot_access_ledger_or_assessment_api(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->getJson('/api/v1/graduate-ledger')->assertForbidden();
        $this->actingAs($cashier)->getJson('/api/v1/assessments')->assertForbidden();
    }
}
