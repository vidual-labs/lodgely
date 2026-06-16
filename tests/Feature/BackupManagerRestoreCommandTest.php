<?php

namespace Tests\Feature;

use App\Support\Backup\BackupManager;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;
use ZipArchive;

class BackupManagerRestoreCommandTest extends TestCase
{
    private function makeArchive(): string
    {
        $dir = sys_get_temp_dir().'/lodgely-test-'.uniqid();
        mkdir($dir);
        $zipPath = $dir.'/backup.zip';

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('manifest.json', json_encode(['app' => 'lodgely', 'db_driver' => 'pgsql']));
        $zip->addFromString('database.dump', 'dummy-dump-contents');
        $zip->close();

        return $zipPath;
    }

    /**
     * Regression: pg_restore's only positional argument is the input
     * archive, so the target database must be passed with -d, never as a
     * trailing positional. The old code appended the database name after
     * the dump file, which made pg_restore fail with "too many
     * command-line arguments (first is '<db>')".
     */
    public function test_pg_restore_passes_database_via_dash_d_and_dump_file_is_the_only_positional(): void
    {
        Process::fake();

        $archive = $this->makeArchive();

        app(BackupManager::class)->restore($archive);

        $db = (string) config('database.connections.pgsql.database');

        Process::assertRan(function ($process) use ($db) {
            $cmd = $process->command;

            if (! is_array($cmd) || ($cmd[0] ?? null) !== 'pg_restore') {
                return false;
            }

            // -d <database> must be present in the flags.
            $dIndex = array_search('-d', $cmd, true);
            if ($dIndex === false || ($cmd[$dIndex + 1] ?? null) !== $db) {
                return false;
            }

            // The final argument must be the dump file, not the db name.
            $last = (string) end($cmd);

            return str_ends_with($last, 'database.dump');
        });

        @unlink($archive);
        @rmdir(dirname($archive));
    }

    /**
     * pg_restore runs in continue-on-error mode: it restores everything it
     * can and exits non-zero with "errors ignored on restore: N". That is a
     * successful restore with skipped statements, not a failure — restore()
     * should return the count instead of throwing.
     */
    public function test_restore_tolerates_ignored_errors_and_returns_the_count(): void
    {
        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'pg_restore: warning: errors ignored on restore: 3',
                exitCode: 1,
            ),
        ]);

        $archive = $this->makeArchive();

        $count = app(BackupManager::class)->restore($archive);

        $this->assertSame(3, $count);

        @unlink($archive);
        @rmdir(dirname($archive));
    }

    /**
     * A genuine pg_restore failure (no "errors ignored" marker) must still
     * surface as an exception so the operator sees a real error.
     */
    public function test_restore_throws_on_a_fatal_failure(): void
    {
        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'pg_restore: error: connection to server at "db" failed',
                exitCode: 1,
            ),
        ]);

        $archive = $this->makeArchive();

        try {
            $this->expectException(\RuntimeException::class);
            app(BackupManager::class)->restore($archive);
        } finally {
            @unlink($archive);
            @rmdir(dirname($archive));
        }
    }

    /**
     * The shared runPg() helper is also used by create(); make sure
     * pg_dump still receives the database via -d and writes with -f.
     */
    public function test_pg_dump_targets_the_database_via_dash_d(): void
    {
        Process::fake();

        app(BackupManager::class)->create();

        $db = (string) config('database.connections.pgsql.database');

        Process::assertRan(function ($process) use ($db) {
            $cmd = $process->command;

            if (! is_array($cmd) || ($cmd[0] ?? null) !== 'pg_dump') {
                return false;
            }

            $dIndex = array_search('-d', $cmd, true);

            return $dIndex !== false
                && ($cmd[$dIndex + 1] ?? null) === $db
                && in_array('-f', $cmd, true);
        });
    }
}
