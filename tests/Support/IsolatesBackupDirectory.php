<?php

namespace Tests\Support;

use Illuminate\Support\Facades\File;

/**
 * Points storage_path() — and so BackupManager::directory() — at a throwaway
 * directory for the test, instead of the install's real
 * storage/app/private/backups.
 *
 * The backup tests create archives there and clear every *.zip between
 * tests. Run against the real directory, as they used to, `php artisan test`
 * inside a live container (where the project directory *is* the live
 * storage) deleted the operator's backup archives and left an empty test
 * archive on the Backups page.
 */
trait IsolatesBackupDirectory
{
    private ?string $isolatedStoragePath = null;

    protected function setUpIsolatedBackupDirectory(): void
    {
        $this->isolatedStoragePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lodgely-test-storage-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->isolatedStoragePath.'/app/private/backups');
        $this->app->useStoragePath($this->isolatedStoragePath);
    }

    protected function tearDownIsolatedBackupDirectory(): void
    {
        if ($this->isolatedStoragePath !== null) {
            File::deleteDirectory($this->isolatedStoragePath);
            $this->isolatedStoragePath = null;
        }
    }
}
