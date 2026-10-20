<?php

namespace Tests\Feature;

use App\Http\Controllers\StaffInputController;
use App\Jobs\SendOrderOfPaymentEmail;
use App\Models\BankAccountInfo;
use App\Models\FormInput;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\StaffInput;
use App\Models\UACS;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Bulk Order of Payment emails are dispatched as queued jobs, so every
 * recipient must get its own job and the progress endpoint must report a
 * real terminal state (sent / failed) instead of leaving recipients
 * "pending" forever.
 */
class BulkEmailOpTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_bulk_email_queues_one_job_per_eligible_recipient(): void
    {
        Queue::fake();

        $staff = User::factory()->staff()->create();
        $first = $this->makeStaffInput();
        $second = $this->makeStaffInput();

        // No staff_input row — not eligible for an email.
        $unprocessed = $this->makeFormInput();

        $response = $this->actingAs($staff)
            ->postJson('/staff/requests/bulk-email-op', [
                'form_input_ids' => [$first->form_input_id, $second->form_input_id, $unprocessed->id],
                'subject' => 'Your Order of Payment',
            ])
            ->assertOk();

        $response->assertJsonPath('success', true);
        $response->assertJsonPath('total', 2);
        $response->assertJsonCount(2, 'form_input_ids');
        $response->assertJsonPath('form_input_ids.0', $first->form_input_id);
        $response->assertJsonPath('form_input_ids.1', $second->form_input_id);

        Queue::assertPushed(SendOrderOfPaymentEmail::class, 2);

        $this->assertSame('pending', $first->fresh()->email_status);
        $this->assertSame('pending', $second->fresh()->email_status);
        $this->assertNotNull($first->fresh()->email_queued_at);
    }

    public function test_email_job_status_reports_pending_recipients_as_incomplete(): void
    {
        Queue::fake();

        $staff = User::factory()->staff()->create();
        $staffInput = $this->makeStaffInput();

        $this->actingAs($staff)
            ->postJson('/staff/requests/bulk-email-op', [
                'form_input_ids' => [$staffInput->form_input_id],
            ])
            ->assertOk();

        $response = $this->actingAs($staff)
            ->postJson('/staff/requests/email-job-status', [
                'form_input_ids' => [$staffInput->form_input_id],
            ])
            ->assertOk();

        $response->assertJsonPath('jobs.0.status', 'pending');
        $response->assertJsonPath('summary.pending', 1);
        $response->assertJsonPath('completed', false);
    }

    public function test_email_job_status_reports_a_sent_recipient(): void
    {
        $staff = User::factory()->staff()->create();
        $staffInput = $this->makeStaffInput();

        $staffInput->update([
            'email_status' => 'sent',
            'emailed_at' => now(),
        ]);

        $response = $this->actingAs($staff)
            ->postJson('/staff/requests/email-job-status', [
                'form_input_ids' => [$staffInput->form_input_id],
            ])
            ->assertOk();

        $response->assertJsonPath('jobs.0.status', 'sent');
        $response->assertJsonPath('summary.sent', 1);
        $response->assertJsonPath('summary.pending', 0);
        $response->assertJsonPath('completed', true);
    }

    public function test_email_job_status_reports_a_failed_recipient_instead_of_leaving_it_pending(): void
    {
        $staff = User::factory()->staff()->create();
        $staffInput = $this->makeStaffInput();

        $staffInput->update([
            'email_status' => 'failed',
            'email_error' => 'SMTP connection refused.',
        ]);

        $response = $this->actingAs($staff)
            ->postJson('/staff/requests/email-job-status', [
                'form_input_ids' => [$staffInput->form_input_id],
            ])
            ->assertOk();

        $response->assertJsonPath('jobs.0.status', 'failed');
        $response->assertJsonPath('jobs.0.error', 'SMTP connection refused.');
        $response->assertJsonPath('summary.failed', 1);
        $response->assertJsonPath('summary.pending', 0);
        $response->assertJsonPath('completed', true);
    }

    public function test_bulk_email_does_not_queue_a_recipient_twice_while_a_job_is_still_queued(): void
    {
        Queue::fake();

        $staff = User::factory()->staff()->create();
        $staffInput = $this->makeStaffInput();

        $this->actingAs($staff)
            ->postJson('/staff/requests/bulk-email-op', [
                'form_input_ids' => [$staffInput->form_input_id],
            ])
            ->assertOk();

        $response = $this->actingAs($staff)
            ->postJson('/staff/requests/bulk-email-op', [
                'form_input_ids' => [$staffInput->form_input_id],
            ])
            ->assertOk();

        Queue::assertPushed(SendOrderOfPaymentEmail::class, 1);
        $this->assertStringContainsString('already queued', $response->json('message'));
    }

    public function test_bulk_email_requeries_a_recipient_whose_job_went_stale(): void
    {
        Queue::fake();

        $staff = User::factory()->staff()->create();
        $staffInput = $this->makeStaffInput();

        $staffInput->update([
            'email_status' => 'pending',
            'email_queued_at' => now()->subMinutes(StaffInputController::EMAIL_QUEUE_WINDOW_MINUTES + 5),
        ]);

        $this->actingAs($staff)
            ->postJson('/staff/requests/bulk-email-op', [
                'form_input_ids' => [$staffInput->form_input_id],
            ])
            ->assertOk();

        Queue::assertPushed(SendOrderOfPaymentEmail::class, 1);
    }

    public function test_bulk_email_clears_a_previous_send_timestamp_for_the_new_attempt(): void
    {
        Queue::fake();

        $staff = User::factory()->staff()->create();
        $staffInput = $this->makeStaffInput();

        $staffInput->update([
            'emailed_at' => now()->subDays(3),
            'email_status' => 'sent',
        ]);

        $this->actingAs($staff)
            ->postJson('/staff/requests/bulk-email-op', [
                'form_input_ids' => [$staffInput->form_input_id],
            ])
            ->assertOk();

        $response = $this->actingAs($staff)
            ->postJson('/staff/requests/email-job-status', [
                'form_input_ids' => [$staffInput->form_input_id],
            ])
            ->assertOk();

        $response->assertJsonPath('jobs.0.status', 'pending');
    }

    public function test_email_job_status_flags_a_job_that_no_worker_has_picked_up(): void
    {
        config(['queue.default' => 'database']);

        $staff = User::factory()->staff()->create();
        $staffInput = $this->makeStaffInput();

        $staffInput->update([
            'email_status' => 'pending',
            'email_queued_at' => now()->subMinutes(5),
        ]);

        $this->insertQueuedJob($staffInput->form_input_id, now()->subMinutes(5));

        $response = $this->actingAs($staff)
            ->postJson('/staff/requests/email-job-status', [
                'form_input_ids' => [$staffInput->form_input_id],
            ])
            ->assertOk();

        $response->assertJsonPath('jobs.0.status', 'pending');
        $response->assertJsonPath('jobs.0.stalled', true);
        $response->assertJsonPath('summary.stalled', 1);
    }

    public function test_email_job_status_does_not_flag_a_job_that_a_worker_already_reserved(): void
    {
        config(['queue.default' => 'database']);

        $staff = User::factory()->staff()->create();
        $staffInput = $this->makeStaffInput();

        $staffInput->update([
            'email_status' => 'pending',
            'email_queued_at' => now()->subMinutes(5),
        ]);

        $this->insertQueuedJob($staffInput->form_input_id, now()->subMinutes(5), reserved: true);

        $response = $this->actingAs($staff)
            ->postJson('/staff/requests/email-job-status', [
                'form_input_ids' => [$staffInput->form_input_id],
            ])
            ->assertOk();

        $response->assertJsonPath('jobs.0.stalled', false);
        $response->assertJsonPath('summary.stalled', 0);
    }

    /**
     * Insert a raw queue job the way the database queue driver stores it.
     *
     * The `jobs` table keeps `created_at` / `reserved_at` as unsigned unix
     * timestamps, not datetimes.
     */
    private function insertQueuedJob(int $formInputId, CarbonInterface $createdAt, bool $reserved = false): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode([
                'displayName' => 'App\\Jobs\\SendOrderOfPaymentEmail',
                'data' => [
                    'commandName' => 'App\\Jobs\\SendOrderOfPaymentEmail',
                    'command' => 'formInputId";i:'.$formInputId.';',
                ],
            ]),
            'attempts' => 0,
            'reserved_at' => $reserved ? $createdAt->getTimestamp() : null,
            'available_at' => $createdAt->getTimestamp(),
            'created_at' => $createdAt->getTimestamp(),
        ]);
    }

    private function makeFormInput(): FormInput
    {
        $membership = Membership::firstOrCreate(
            ['member_code' => 'MEM-'.uniqid()],
            ['member_desc' => 'Regular Member'],
        );

        $paymentDetail = PaymentDetailOption::firstOrCreate(
            ['payment_desc' => 'Payment Option '.uniqid()],
        );

        return FormInput::create([
            'reference_number' => 'OP-'.uniqid(),
            'email' => 'client@example.com',
            'contact_num' => '09171234567',
            'firstname_or_office' => 'Test',
            'lastname_or_agency' => 'Client',
            'office_or_college' => 'Test Office',
            'position_or_designation' => 'Tester',
            'address' => 'Test Address',
            'amount' => 500,
            'request_type' => 'New Request',
            'membership_id' => $membership->id,
            'payment_detail_option_id' => $paymentDetail->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function makeStaffInput(array $attributes = []): StaffInput
    {
        $formInput = $this->makeFormInput();

        $bank = BankAccountInfo::firstOrCreate(
            ['account_num' => '123456789'],
            ['account_name' => 'Main', 'bank_name' => 'Landbank', 'fund_cluster' => '01'],
        );
        $uacs = UACS::firstOrCreate(
            ['object_code' => '4020101000'],
            ['account_title' => 'Tuition Fees'],
        );

        return StaffInput::create(array_merge([
            'form_input_id' => $formInput->id,
            'fundcluster_id' => $bank->id,
            'ref_date' => '2026-09-01',
            'uacs_id' => $uacs->id,
            'status' => 'processed',
        ], $attributes));
    }
}
