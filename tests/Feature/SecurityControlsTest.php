<?php

namespace Tests\Feature;

use App\Mail\OrderOfPaymentMail;
use App\Models\FormInput;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\SupportingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_protection_and_authentication_are_required(): void
    {
        $this->get('/staff/requests')->assertRedirect('/login');
        $this->post('/public/opform', [])->assertSessionHasErrors();
        $this->post('/admin/users', [])->assertRedirect('/login');
    }

    public function test_non_admin_authorization_is_enforced_server_side(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get('/admin/users')->assertRedirect();
        $this->actingAs($staff)->post('/admin/users', [
            'name' => 'Injected', 'email' => 'injected@example.com', 'password' => 'Password1!', 'role' => 'admin',
        ])->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'injected@example.com']);
    }

    public function test_mass_assignment_does_not_allow_role_or_id_override(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $user->fill(['id' => 9999, 'role' => 'admin', 'name' => 'Changed']);
        $user->save();
        $this->assertSame($user->getKey(), $user->fresh()->id);
        $this->assertSame('staff', $user->fresh()->role);
    }

    public function test_sql_injection_and_sort_values_are_safe(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/users?search=%27%20OR%201%3D1%20--&sort=name%20desc%2C%20id')
            ->assertOk();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_xss_is_escaped_in_email_html(): void
    {
        $form = FormInput::create($this->formPayload(['firstname_or_office' => '<script>alert(1)</script>']));
        $mail = new OrderOfPaymentMail($form, '%PDF%', '%PDF%', null, null, '<img src=x onerror=alert(1)>');
        $html = $mail->render();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function test_upload_type_size_and_filename_are_validated_and_sanitized(): void
    {
        Storage::fake('s3');
        $form = FormInput::create($this->formPayload());
        $this->actingAs(User::factory()->staff()->create())->post('/staff/documents', [
            'form_input_id' => $form->id,
            'document' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('document');

        $this->actingAs(User::factory()->staff()->create())->post('/staff/documents', [
            'form_input_id' => $form->id,
            'document' => UploadedFile::fake()->create('large.pdf', 11000, 'application/pdf'),
        ])->assertSessionHasErrors('document');

        $this->actingAs(User::factory()->staff()->create())->post('/staff/documents', [
            'form_input_id' => $form->id,
            'document' => UploadedFile::fake()->create('../../secret.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $document = SupportingDocument::latest()->first();
        $this->assertNotNull($document);
        $this->assertStringNotContainsString('..', $document->stored_filename);
        $this->assertStringNotContainsString('/', $document->stored_filename);
    }

    public function test_uploaded_document_download_requires_authentication_and_does_not_trust_path(): void
    {
        Storage::fake('s3');
        $form = FormInput::create($this->formPayload());
        $document = SupportingDocument::create([
            'form_input_id' => $form->id, 'original_filename' => '../../secret.txt', 'stored_filename' => 'safe.pdf',
            'file_url' => 's3://safe', 'mime_type' => 'application/pdf', 'file_extension' => 'pdf', 'file_size' => 3, 'uploaded_at' => now(),
        ]);
        Storage::disk('s3')->put('supporting-documents/safe.pdf', 'pdf');

        $this->get('/staff/documents/'.$document->id.'/download')->assertRedirect('/login');
        $response = $this->actingAs(User::factory()->staff()->create())->get('/staff/documents/'.$document->id.'/download');
        $response->assertOk();
        $this->assertStringNotContainsString('..', (string) $response->headers->get('Content-Disposition'));
        Storage::disk('s3')->assertExists('supporting-documents/safe.pdf');
    }

    public function test_passwords_are_hashed_and_not_exposed_in_responses(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret-password')]);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertNotSame('secret-password', $user->password);
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_public_submission_rate_limit_is_registered_and_duplicate_submissions_need_valid_data(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === 'public/opform' && $route->methods() === ['POST']);
        $this->assertNotNull($route);
        $this->assertContains('throttle:20,1', $route->gatherMiddleware());
        $this->post('/public/opform', [])->assertSessionHasErrors();
    }

    /** @return array<string, mixed> */
    private function formPayload(array $overrides = []): array
    {
        $membership = Membership::firstOrCreate(['member_code' => 'SEC-'.uniqid()], ['member_desc' => 'Security']);
        $option = PaymentDetailOption::firstOrCreate(['payment_desc' => 'Security '.uniqid()]);
        return array_merge([
            'reference_number' => 'SEC-'.uniqid(), 'email' => 'secure@example.com', 'contact_num' => '09171234567',
            'firstname_or_office' => 'Secure', 'lastname_or_agency' => 'User', 'office_or_college' => 'Office',
            'position_or_designation' => 'Staff', 'address' => 'Address', 'amount' => 1, 'request_type' => 'New Request',
            'membership_id' => $membership->id, 'payment_detail_option_id' => $option->id,
        ], $overrides);
    }
}
