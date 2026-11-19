<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LedgerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_requires_a_supported_file(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->postJson('/api/v1/graduate-ledger/import', [
            'file' => UploadedFile::fake()->create('ledger.txt', 1, 'text/plain'),
        ])->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
    }

    public function test_import_accepts_csv_and_returns_structured_result(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $csv = UploadedFile::fake()->createWithContent('ledger.csv', "STUDENT NUMBER,FULL NAME,AMOUNT\nS1,Test Student,100\n");
        $response = $this->actingAs($staff)->postJson('/api/v1/graduate-ledger/import', ['file' => $csv]);
        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'imported', 'skipped', 'duplicates', 'warnings', 'detail_available'])
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('duplicates', 0);
    }
}
