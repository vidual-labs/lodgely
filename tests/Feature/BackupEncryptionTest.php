<?php

namespace Tests\Feature;

use App\Support\Backup\ArchiveCipher;
use App\Support\Backup\BackupManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Cover for optional backup encryption.
 *
 * The load-bearing test here is
 * test_a_plain_archive_still_restores_when_a_passphrase_is_configured: every
 * archive an operator already has on disk predates this feature, and a restore
 * is what you reach for on your worst day. Breaking it would be worse than
 * shipping no encryption at all.
 */
class BackupEncryptionTest extends TestCase
{
    private const PASSPHRASE = 'correct horse battery staple';

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearArchives();
    }

    protected function tearDown(): void
    {
        $this->clearArchives();

        parent::tearDown();
    }

    /**
     * Remove archives left by a previous test, but not the directory itself —
     * it holds a tracked .gitkeep, and deleting the directory quietly removes
     * that from the working tree.
     */
    private function clearArchives(): void
    {
        $directory = app(BackupManager::class)->directory();

        if (! is_dir($directory)) {
            return;
        }

        foreach (File::glob($directory.DIRECTORY_SEPARATOR.'*.zip') as $archive) {
            File::delete($archive);
        }
    }

    /** Build an archive the way BackupManager would, with real dump contents. */
    private function makeArchive(string $dumpContents, ?string $passphrase): string
    {
        $dir = sys_get_temp_dir().'/lodgely-enc-test-'.uniqid();
        mkdir($dir);
        $zipPath = $dir.'/backup.zip';

        $manifest = ['app' => 'lodgely', 'db_driver' => 'pgsql'];

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);

        if ($passphrase === null) {
            $zip->addFromString('database.dump', $dumpContents);
        } else {
            $plainPath = $dir.'/plain.dump';
            $encPath = $dir.'/database.dump.enc';
            file_put_contents($plainPath, $dumpContents);

            $manifest['encrypted'] = true;
            $manifest['encryption'] = (new ArchiveCipher())->encryptFile($plainPath, $encPath, $passphrase);

            $zip->addFile($encPath, 'database.dump.enc');
        }

        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();

        return $zipPath;
    }

    /**
     * Run a restore and return the bytes that were actually handed to
     * pg_restore — the temp extract directory is cleaned up in restore()'s
     * finally block, so the file has to be read from inside the process fake.
     */
    private function dumpHandedToPgRestore(string $archive): string
    {
        $captured = '';

        Process::fake(['*' => function ($process) use (&$captured) {
            $dumpPath = (string) end($process->command);
            if (is_file($dumpPath)) {
                $captured = (string) file_get_contents($dumpPath);
            }

            return Process::result();
        }]);

        app(BackupManager::class)->restore($archive);

        return $captured;
    }

    // ------------------------------------------------------- default: off

    public function test_archives_are_unencrypted_by_default(): void
    {
        config(['lodgely.backups.passphrase' => null]);
        Process::fake();

        $backup = app(BackupManager::class)->create();

        $zip = new ZipArchive();
        $zip->open($backup['path']);

        $this->assertNotFalse($zip->locateName('database.dump'), 'Default archives must keep the plain dump entry.');
        $this->assertFalse($zip->locateName('database.dump.enc'));

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();

        $this->assertArrayNotHasKey('encrypted', $manifest);
    }

    // ------------------------------------------------ backwards compatibility

    public function test_a_plain_archive_still_restores_when_a_passphrase_is_configured(): void
    {
        // The upgrade case: operator sets a passphrase, then needs to restore
        // an archive taken before they did.
        config(['lodgely.backups.passphrase' => self::PASSPHRASE]);

        $archive = $this->makeArchive('PGDMP-plain-payload', passphrase: null);

        $this->assertSame('PGDMP-plain-payload', $this->dumpHandedToPgRestore($archive));
    }

    // ------------------------------------------------------------ encrypted

    public function test_encrypted_archive_hides_the_dump_and_round_trips(): void
    {
        config(['lodgely.backups.passphrase' => self::PASSPHRASE]);

        $secret = 'PGDMP lead: ada@example.com +49 30 1234567';
        $archive = $this->makeArchive($secret, self::PASSPHRASE);

        $zip = new ZipArchive();
        $zip->open($archive);
        $stored = (string) $zip->getFromName('database.dump.enc');
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();

        $this->assertTrue($manifest['encrypted']);
        $this->assertSame('aes-256-gcm', $manifest['encryption']['cipher']);
        $this->assertStringNotContainsString('ada@example.com', $stored);
        $this->assertStringNotContainsString('PGDMP', $stored);

        // ...and it still comes back out intact.
        $this->assertSame($secret, $this->dumpHandedToPgRestore($archive));
    }

    public function test_restoring_an_encrypted_archive_without_a_passphrase_explains_itself(): void
    {
        $archive = $this->makeArchive('PGDMP-secret', self::PASSPHRASE);

        config(['lodgely.backups.passphrase' => null]);
        Process::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/LODGELY_BACKUP_PASSPHRASE/');

        app(BackupManager::class)->restore($archive);
    }

    public function test_restoring_with_the_wrong_passphrase_fails(): void
    {
        $archive = $this->makeArchive('PGDMP-secret', self::PASSPHRASE);

        config(['lodgely.backups.passphrase' => 'not-the-right-one']);
        Process::fake();

        $this->expectException(RuntimeException::class);

        app(BackupManager::class)->restore($archive);
    }

    // ------------------------------------------------------- cipher itself

    public function test_cipher_round_trips_payloads_larger_than_one_frame(): void
    {
        // Two and a bit 1 MiB frames, so the chunking loop and the frame
        // counter are actually exercised rather than a single-frame happy path.
        $plaintext = random_bytes(2 * 1048576 + 4242);

        $dir = sys_get_temp_dir().'/lodgely-cipher-'.uniqid();
        mkdir($dir);

        file_put_contents($dir.'/plain', $plaintext);

        $cipher = new ArchiveCipher();
        $meta = $cipher->encryptFile($dir.'/plain', $dir.'/enc', self::PASSPHRASE);
        $cipher->decryptFile($dir.'/enc', $dir.'/out', self::PASSPHRASE, $meta);

        $this->assertSame(strlen($plaintext), $meta['plaintext_bytes']);
        $this->assertSame(md5($plaintext), md5_file($dir.'/out'));

        File::deleteDirectory($dir);
    }

    public function test_cipher_rejects_a_truncated_archive(): void
    {
        $dir = sys_get_temp_dir().'/lodgely-cipher-'.uniqid();
        mkdir($dir);

        file_put_contents($dir.'/plain', str_repeat('lead-data', 5000));

        $cipher = new ArchiveCipher();
        $meta = $cipher->encryptFile($dir.'/plain', $dir.'/enc', self::PASSPHRASE);

        // Lop off the tail — every surviving frame still authenticates, so only
        // the recorded plaintext length catches this.
        $truncated = substr((string) file_get_contents($dir.'/enc'), 0, 512);
        file_put_contents($dir.'/enc', $truncated);

        try {
            $this->expectException(RuntimeException::class);
            $cipher->decryptFile($dir.'/enc', $dir.'/out', self::PASSPHRASE, $meta);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    // ---------------------------------------------------------- retention

    public function test_pruning_is_off_by_default(): void
    {
        config(['lodgely.backups.keep' => null]);
        Process::fake();

        $manager = app(BackupManager::class);

        for ($i = 0; $i < 3; $i++) {
            $this->travel($i)->seconds();  // filenames are second-resolution
            $manager->create();
        }
        $this->travelBack();

        $this->assertCount(3, $manager->list());
    }

    public function test_pruning_keeps_only_the_configured_number_of_archives(): void
    {
        config(['lodgely.backups.keep' => 2]);
        Process::fake();

        $manager = app(BackupManager::class);
        $created = [];

        for ($i = 0; $i < 4; $i++) {
            $this->travel($i)->seconds();
            $created[] = $manager->create()['filename'];
        }
        $this->travelBack();

        $remaining = array_column($manager->list(), 'filename');

        $this->assertCount(2, $remaining);
        $this->assertContains($created[3], $remaining, 'The newest archive must survive pruning.');
        $this->assertNotContains($created[0], $remaining, 'The oldest archive should have been pruned.');
    }

    /**
     * The artisan command's --keep used to walk the archive list itself, which
     * meant it could delete the backup it had just taken when two archives
     * shared an mtime second. It now delegates to the same prune().
     */
    public function test_the_create_command_keep_option_never_prunes_the_backup_it_just_took(): void
    {
        config(['lodgely.backups.keep' => null]);
        Process::fake();

        $manager = app(BackupManager::class);

        for ($i = 0; $i < 3; $i++) {
            $this->travel($i)->seconds();
            $manager->create();
        }
        $this->travelBack();

        $this->artisan('lodgely:backup:create', ['--keep' => 1])->assertSuccessful();

        $remaining = $manager->list();

        $this->assertCount(1, $remaining);
        $this->assertFileExists($remaining[0]['path']);
    }
}
