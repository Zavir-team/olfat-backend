<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationsHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_ok_and_security_headers(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.cache.ok', true)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_cron_endpoint_requires_matching_secret(): void
    {
        config(['app.cron_secret' => 'top-secret-value']);

        $this->getJson('/api/cron/schedule')->assertUnauthorized();

        $this->getJson('/api/cron/schedule', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        $this->getJson('/api/cron/schedule', ['Authorization' => 'Bearer top-secret-value'])
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_cron_endpoint_is_disabled_when_no_secret_configured(): void
    {
        config(['app.cron_secret' => '']);

        $this->getJson('/api/cron/schedule', ['Authorization' => 'Bearer '])->assertUnauthorized();
    }

    public function test_business_writes_are_audited_and_only_admins_can_read_the_trail(): void
    {
        $staff = User::factory()->create();
        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/customers', ['name' => 'Audited Customer'])->assertCreated();

        $customer = Customer::query()->where('name', 'Audited Customer')->firstOrFail();

        $this->putJson("/api/v1/customers/{$customer->id}", ['name' => 'Renamed Customer'])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'user_id' => $staff->id,
        ]);

        $update = AuditLog::query()
            ->where('event', 'updated')
            ->where('auditable_id', $customer->id)
            ->firstOrFail();

        $this->assertSame('Audited Customer', $update->old_values['name']);
        $this->assertSame('Renamed Customer', $update->new_values['name']);

        $this->getJson('/api/v1/audit-logs')->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/audit-logs?auditable_type=Customer&auditable_id='.$customer->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2);
    }

    public function test_audit_log_never_stores_password_hashes(): void
    {
        $user = User::factory()->create(['password' => 'super-secret-password']);

        $log = AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('event', 'created')
            ->firstOrFail();

        $this->assertSame('[redacted]', $log->new_values['password']);
        $this->assertSame('[redacted]', $log->new_values['remember_token']);
    }

    public function test_db_backup_command_writes_a_verified_compressed_dump(): void
    {
        Storage::fake('local');
        Customer::factory()->create(['name' => 'Backup Customer']);

        $this->artisan('db:backup', ['--disk' => 'local'])->assertSuccessful();

        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.sql.gz', $files[0]);

        $sql = gzdecode(Storage::disk('local')->get($files[0]));
        $this->assertStringContainsString('-- table: customers', $sql);
        $this->assertStringContainsString('Backup Customer', $sql);
    }

    public function test_db_integrity_check_passes_on_a_consistent_database(): void
    {
        User::factory()->admin()->create();
        Customer::factory()->count(2)->create();

        $this->artisan('db:integrity-check')
            ->expectsOutputToContain('Integrity check passed')
            ->assertSuccessful();
    }
}
