<?php

namespace Tests\Feature\Api;

use App\Models\FormInput;
use App\Models\SupportingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportingDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_upload_list_download_and_delete_document(): void
    {
        Storage::fake('s3');
        $staff = User::factory()->create(['role' => 'staff']);
        $form = FormInput::create([
            'reference_number' => 'OP-DOC-1', 'email' => 'doc@example.com', 'contact_num' => '09123456789',
            'firstname_or_office' => 'Test', 'lastname_or_agency' => 'User', 'position_or_designation' => 'Staff',
            'office_or_college' => 'General', 'address' => 'Dumaguete', 'amount' => 100,
            'request_type' => 'New Request',
        ]);

        $upload = $this->actingAs($staff)->postJson('/api/v1/documents', [
            'form_input_id' => $form->id,
            'document' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
        ]);

        $upload->assertCreated()->assertJsonPath('data.original_filename', 'receipt.pdf');
        $document = SupportingDocument::firstOrFail();
        Storage::disk('s3')->assertExists('supporting-documents/'.$document->stored_filename);

        $this->actingAs($staff)->getJson('/api/v1/documents')->assertOk()->assertJsonPath('data.0.id', $document->id);
        $this->actingAs($staff)->get('/api/v1/documents/'.$document->id.'/download')->assertOk();
        $this->actingAs($staff)->deleteJson('/api/v1/documents/'.$document->id)->assertNoContent();
        Storage::disk('s3')->assertMissing('supporting-documents/'.$document->stored_filename);
    }

    public function test_non_staff_cannot_access_documents(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->getJson('/api/v1/documents')->assertForbidden();
    }
}
