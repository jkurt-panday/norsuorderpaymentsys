<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeploymentCiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ci_tooling_and_lockfiles_are_present(): void
    {
        $this->assertFileExists(base_path('composer.lock'));
        $this->assertFileExists(base_path('pnpm-lock.yaml'));
        $this->assertFileExists(base_path('composer.json'));
        $this->assertFileExists(base_path('package.json'));
        $this->assertFileExists(base_path('vite.config.ts'));
    }

    public function test_composer_and_frontend_scripts_define_required_ci_steps(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $package = json_decode((string) file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach (['lint:check', 'types:check', 'test'] as $script) {
            $this->assertArrayHasKey($script, $composer['scripts']);
        }
        foreach (['lint:check', 'format:check', 'types:check', 'build'] as $script) {
            $this->assertArrayHasKey($script, $package['scripts']);
        }
        $this->assertSame('pnpm@12.9.1', $package['packageManager']);
    }

    public function test_clean_application_boots_with_required_environment_template_and_key_generation(): void
    {
        $this->assertFileExists(base_path('.env.example'));
        $env = (string) file_get_contents(base_path('.env.example'));
        foreach (['APP_KEY=', 'APP_ENV=', 'DB_CONNECTION=', 'QUEUE_CONNECTION=', 'FILESYSTEM_DISK=', 'MAIL_MAILER='] as $key) {
            $this->assertStringContainsString($key, $env);
        }
        $this->assertTrue((bool) preg_match('/APP_KEY=/m', $env));
    }

    public function test_database_migrations_have_run_and_core_tables_exist(): void
    {
        foreach (['users', 'sessions', 'jobs', 'failed_jobs', 'cache', 'form_inputs', 'graduate_ledgers', 'law_school_ledgers', 'uacs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing migrated table: {$table}");
        }
    }

    public function test_production_cache_commands_and_routes_are_available(): void
    {
        $this->assertSame(0, Artisan::call('config:clear'));
        $this->assertSame(0, Artisan::call('route:list', ['--json' => true]));
        $this->assertSame(0, Artisan::call('view:cache'));
        Artisan::call('view:clear');
        $this->assertSame(0, Artisan::call('config:cache'));
        Artisan::call('config:clear');
    }

    public function test_health_endpoint_and_scheduled_command_bootstrap_are_available(): void
    {
        $this->get('/up')->assertOk();
        $this->assertFileExists(base_path('routes/console.php'));
        $this->assertStringContainsString('Artisan::command', (string) file_get_contents(base_path('routes/console.php')));
    }

    public function test_queue_worker_and_pdf_browser_dependencies_are_configured(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('queue:work', $composer['scripts']);
        $this->assertFileExists(base_path('node_modules/puppeteer/package.json'));
        $this->assertFileExists(base_path('vendor/spatie/browsershot/src/Browsershot.php'));
        $this->assertTrue(extension_loaded('pdo'));
        $this->assertTrue(extension_loaded('mbstring'));
        $this->assertTrue(extension_loaded('fileinfo'));
    }

    public function test_storage_directories_are_writable_for_runtime_artifacts(): void
    {
        foreach ([storage_path('framework/cache'), storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs')] as $directory) {
            $this->assertDirectoryExists($directory);
            $this->assertTrue(is_writable($directory), "Storage directory is not writable: {$directory}");
        }
    }

    public function test_deployment_rollback_artifacts_are_not_present_without_a_deployment_tool(): void
    {
        $this->assertFileDoesNotExist(base_path('artisan.rollback'));
        $this->assertFileDoesNotExist(base_path('deploy.sh'));
        $this->assertFileDoesNotExist(base_path('.github/workflows/deploy.yml'));
    }
}
