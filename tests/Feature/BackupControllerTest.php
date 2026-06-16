<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Backup\BackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class BackupControllerTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::firstOrCreate(
            ['id' => Tenant::DEFAULT_ID],
            ['slug' => 'default', 'name' => 'lodgely'],
        );
    }

    private function operator(string $email = 'ops@example.com'): User
    {
        $this->tenant();

        return User::create([
            'name' => 'Op',
            'email' => $email,
            'password' => Hash::make('p'),
            'role' => 'operator',
            'is_active' => true,
        ]);
    }

    private function client(string $email = 'c@example.com'): User
    {
        $this->tenant();

        return User::create([
            'name' => 'Client',
            'email' => $email,
            'password' => Hash::make('p'),
            'role' => 'client',
            'is_active' => true,
        ]);
    }

    private function zipUpload(): UploadedFile
    {
        return UploadedFile::fake()->create('lodgely-backup.zip', 64, 'application/zip');
    }

    public function test_client_cannot_create_backup(): void
    {
        $this->actingAs($this->client())
            ->post(route('settings.backups.create'))
            ->assertForbidden();
    }

    public function test_operator_can_create_backup_via_native_form(): void
    {
        $manager = Mockery::mock(BackupManager::class);
        $manager->shouldReceive('create')->once()->andReturn([
            'filename' => 'lodgely-backup-20260101-120000.zip',
            'path' => '/tmp/x.zip',
            'size' => 2048,
            'created_at' => now()->toIso8601String(),
        ]);
        $this->instance(BackupManager::class, $manager);

        $this->actingAs($this->operator())
            ->post(route('settings.backups.create'))
            ->assertRedirect(route('settings.backups'))
            ->assertSessionHas('backups.notice');
    }

    public function test_operator_can_delete_backup_via_native_form(): void
    {
        $manager = Mockery::mock(BackupManager::class);
        $manager->shouldReceive('delete')->once()->with('lodgely-backup-20260101-120000.zip');
        $this->instance(BackupManager::class, $manager);

        $this->actingAs($this->operator())
            ->post(route('settings.backups.delete'), ['filename' => 'lodgely-backup-20260101-120000.zip'])
            ->assertRedirect(route('settings.backups'))
            ->assertSessionHas('backups.notice');
    }

    public function test_client_cannot_restore(): void
    {
        $manager = Mockery::mock(BackupManager::class);
        $manager->shouldNotReceive('restore');
        $this->instance(BackupManager::class, $manager);

        $this->actingAs($this->client())
            ->post(route('settings.backups.restore'), [
                'restore_confirmation' => 'RESTORE',
                'restore_file' => $this->zipUpload(),
            ])
            ->assertForbidden();
    }

    public function test_restore_rejects_wrong_confirmation_word(): void
    {
        $manager = Mockery::mock(BackupManager::class);
        $manager->shouldNotReceive('restore');
        $this->instance(BackupManager::class, $manager);

        $this->actingAs($this->operator())
            ->post(route('settings.backups.restore'), [
                'restore_confirmation' => 'restore please',
                'restore_file' => $this->zipUpload(),
            ])
            ->assertRedirect(route('settings.backups'))
            ->assertSessionHas('backups.error');
    }

    public function test_restore_requires_a_file(): void
    {
        $manager = Mockery::mock(BackupManager::class);
        $manager->shouldNotReceive('restore');
        $this->instance(BackupManager::class, $manager);

        $this->actingAs($this->operator())
            ->post(route('settings.backups.restore'), [
                'restore_confirmation' => 'RESTORE',
            ])
            ->assertRedirect(route('settings.backups'))
            ->assertSessionHas('backups.error');
    }

    public function test_valid_restore_runs_the_manager_and_signs_the_operator_out(): void
    {
        $manager = Mockery::mock(BackupManager::class);
        $manager->shouldReceive('restore')->once()->andReturn(0);
        $this->instance(BackupManager::class, $manager);

        $this->actingAs($this->operator())
            ->post(route('settings.backups.restore'), [
                'restore_confirmation' => '  restore  ', // trimmed + uppercased to RESTORE
                'restore_file' => $this->zipUpload(),
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status')
            // Operators must be told encrypted integration creds may need re-entry.
            ->assertSessionHas('warning');

        $this->assertGuest();
    }

    public function test_restore_surfaces_the_skipped_statement_count(): void
    {
        $manager = Mockery::mock(BackupManager::class);
        $manager->shouldReceive('restore')->once()->andReturn(4);
        $this->instance(BackupManager::class, $manager);

        $this->actingAs($this->operator())
            ->post(route('settings.backups.restore'), [
                'restore_confirmation' => 'RESTORE',
                'restore_file' => $this->zipUpload(),
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', fn ($v) => str_contains((string) $v, '4'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
