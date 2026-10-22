<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Support\Header;
use Tests\TestCase;

/**
 * Inertia asset versioning makes the server answer with 409 Conflict when the
 * browser's asset version no longer matches (e.g. after a rebuild). That is
 * useful in production but only causes a conflict loop in local development,
 * where Vite serves the assets directly.
 */
class InertiaAssetVersionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_stale_asset_version_does_not_conflict_in_local(): void
    {
        $staff = User::factory()->staff()->create();

        $response = $this->actingAs($staff)->get('/staff/requests', [
            Header::INERTIA => 'true',
            Header::VERSION => 'a-stale-version-from-an-older-build',
        ]);

        $response->assertOk();
        $this->assertNotSame(409, $response->getStatusCode());
    }

    public function test_a_request_without_an_asset_version_does_not_conflict(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/staff/requests', [
            Header::INERTIA => 'true',
        ])->assertOk();
    }
}
