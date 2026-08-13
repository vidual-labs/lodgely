<?php

namespace App\Support\Backup;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use ZipArchive;

/**
 * Whole-database backup/restore for self-hosted installs.
 *
 * A backup is a zip containing a `pg_dump -Fc` archive plus a small
 * manifest.json (app version, created_at, db driver) so a restore can
 * sanity-check the file before handing it to pg_restore. Files live on
 * the `local` disk under backups/ — same private root as imports.
 *
 * Two things about that dump are worth stating plainly, because the name
 * "backup" hides them: it contains every lead's name, email, phone and
 * message body in cleartext, and it outlives `retention_until` (the purge
 * command deletes rows from the database, not from archives taken earlier).
 *
 * So the dump can optionally be encrypted with a passphrase — see
 * {@see ArchiveCipher}. This is opt-in via LODGELY_BACKUP_PASSPHRASE and
 * off by default, because turning it on silently would hand operators
 * archives they cannot restore without a setting they never chose. The
 * manifest records which shape an archive is, and restore() reads it, so
 * archives written before this existed still restore unchanged.
 */
class BackupManager
{
    private const DIRECTORY = 'backups';

    private const MANIFEST_ENTRY = 'manifest.json';

    private const DUMP_ENTRY = 'database.dump';

    /** Encrypted archives carry the dump under this name instead of DUMP_ENTRY. */
    private const ENCRYPTED_DUMP_ENTRY = 'database.dump.enc';

    public function __construct(private readonly ArchiveCipher $cipher = new ArchiveCipher()) {}

    /** @return array{filename: string, path: string, size: int, created_at: string} */
    public function create(): array
    {
        $this->ensureDirectory();

        $timestamp = now()->format('Ymd-His');
        $filename = "lodgely-backup-{$timestamp}.zip";
        $zipPath = $this->absolutePath($filename);

        $dumpPath = tempnam(sys_get_temp_dir(), 'lodgely-dump-');
        if ($dumpPath === false) {
            throw new RuntimeException('Could not allocate a temporary file for the database dump.');
        }

        try {
            $result = $this->runPg('pg_dump', ['-Fc', '-f', $dumpPath]);

            if ($result->failed()) {
                throw new RuntimeException('pg_dump failed: '.trim($result->errorOutput() ?: $result->output()));
            }

            $manifest = [
                'app' => 'lodgely',
                'app_version' => (string) config('lodgely.version'),
                'db_driver' => 'pgsql',
                'created_at' => now()->toIso8601String(),
            ];

            $passphrase = $this->passphrase();
            $encryptedPath = null;

            if ($passphrase !== null) {
                $encryptedPath = $dumpPath.'.enc';
                $manifest['encrypted'] = true;
                $manifest['encryption'] = $this->cipher->encryptFile($dumpPath, $encryptedPath, $passphrase);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Could not create backup archive at {$zipPath}.");
            }
            $zip->addFromString(self::MANIFEST_ENTRY, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($encryptedPath !== null) {
                $zip->addFile($encryptedPath, self::ENCRYPTED_DUMP_ENTRY);
            } else {
                $zip->addFile($dumpPath, self::DUMP_ENTRY);
            }

            $zip->close();

            // addFile() only reads the source when close() streams the entry
            // out, so the encrypted temp file has to survive until here.
            if ($encryptedPath !== null) {
                @unlink($encryptedPath);
            }
        } finally {
            @unlink($dumpPath);
        }

        // Read the size before pruning: on a filesystem with second-resolution
        // mtimes several archives can tie, and stat()ing a file prune() just
        // removed is an avoidable warning.
        $size = filesize($zipPath) ?: 0;

        $this->prune(keepAlways: $filename);


        return [
            'filename' => $filename,
            'path' => $zipPath,
            'size' => $size,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Trim the archive directory to the $keep most recent files, returning the
     * filenames removed.
     *
     * $keep defaults to `lodgely.backups.keep`, which is itself null — keep
     * everything. Pruning is opt-in on purpose: an upgrade that silently
     * started deleting an operator's backups would be a far worse bug than a
     * full disk.
     *
     * $keepAlways is the archive that was just written. Sorting by mtime is
     * ambiguous when two archives land in the same second, and "the backup you
     * just took gets deleted by its own prune" is the one outcome this must
     * never produce — so it is excluded from the candidates outright rather
     * than trusted to sort first.
     *
     * @return list<string> filenames that were deleted
     */
    public function prune(?int $keep = null, ?string $keepAlways = null): array
    {
        $keep ??= config('lodgely.backups.keep');

        if ($keep === null) {
            return [];
        }

        $keep = max(1, (int) $keep);

        $candidates = array_values(array_filter(
            $this->list(),
            static fn (array $archive) => $archive['filename'] !== $keepAlways,
        ));

        // The just-created archive occupies one of the slots.
        $budget = max(0, $keep - ($keepAlways === null ? 0 : 1));

        $pruned = [];

        foreach (array_slice($candidates, $budget) as $stale) {
            File::delete($stale['path']);
            $pruned[] = $stale['filename'];
        }

        return $pruned;
    }

    /** The configured backup passphrase, or null when encryption is switched off. */
    private function passphrase(): ?string
    {
        $passphrase = (string) (config('lodgely.backups.passphrase') ?? '');

        return $passphrase !== '' ? $passphrase : null;
    }

    /** @return list<array{filename: string, path: string, size: int, created_at: string}> */
    public function list(): array
    {
        $this->ensureDirectory();

        $files = collect(File::files($this->directory()))
            ->filter(fn ($file) => $file->getExtension() === 'zip')
            ->map(fn ($file) => [
                'filename' => $file->getFilename(),
                'path' => $file->getPathname(),
                'size' => $file->getSize(),
                'created_at' => date('c', $file->getMTime()),
            ])
            // mtime first, filename as tiebreaker: filenames embed the
            // creation timestamp, so this stays stable when several archives
            // share an mtime second (which sortByDesc alone does not).
            ->sortByDesc(fn (array $archive) => [$archive['created_at'], $archive['filename']])
            ->values();

        return $files->all();
    }

    public function delete(string $filename): void
    {
        $path = $this->absolutePath($this->safeFilename($filename));

        if (File::exists($path)) {
            File::delete($path);
        }
    }

    public function absolutePath(string $filename): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.$this->safeFilename($filename);
    }

    /**
     * Restore the database from a backup archive. This is destructive —
     * pg_restore runs with --clean --if-exists, dropping and recreating
     * every object the dump describes before the running app reconnects.
     *
     * @return int Number of statements pg_restore skipped ("errors ignored
     *             on restore"). 0 means a clean restore; a positive value
     *             means the data is in but some objects were dropped/created
     *             with non-fatal errors (typically DROPs of things that did
     *             not exist yet under --clean). Genuine failures throw.
     */
    public function restore(string $archivePath): int
    {
        if (! File::exists($archivePath)) {
            throw new RuntimeException('Backup file not found.');
        }

        $extractDir = $archivePath.'-extract';
        File::ensureDirectoryExists($extractDir);

        try {
            $zip = new ZipArchive();
            if ($zip->open($archivePath) !== true) {
                throw new RuntimeException('Could not open the uploaded file as a backup archive.');
            }

            // Which entry holds the dump tells us the archive's shape: plain
            // archives (everything written before encryption existed) carry
            // DUMP_ENTRY, encrypted ones ENCRYPTED_DUMP_ENTRY.
            $isEncrypted = $zip->locateName(self::ENCRYPTED_DUMP_ENTRY) !== false;
            $dumpEntry = $isEncrypted ? self::ENCRYPTED_DUMP_ENTRY : self::DUMP_ENTRY;

            if ($zip->locateName(self::MANIFEST_ENTRY) === false || $zip->locateName($dumpEntry) === false) {
                $zip->close();
                throw new RuntimeException('This file does not look like a lodgely backup archive (missing manifest or database dump).');
            }

            $zip->extractTo($extractDir, [self::MANIFEST_ENTRY, $dumpEntry]);
            $zip->close();

            $manifestJson = File::get($extractDir.DIRECTORY_SEPARATOR.self::MANIFEST_ENTRY);
            $manifest = json_decode($manifestJson, true);

            if (! is_array($manifest) || ($manifest['app'] ?? null) !== 'lodgely') {
                throw new RuntimeException('This archive does not carry a valid lodgely backup manifest.');
            }

            $dumpPath = $extractDir.DIRECTORY_SEPARATOR.$dumpEntry;

            if ($isEncrypted) {
                $passphrase = $this->passphrase();

                if ($passphrase === null) {
                    throw new RuntimeException(
                        'This backup is encrypted, but no LODGELY_BACKUP_PASSPHRASE is configured on this server. '
                        .'Set it to the passphrase used when the archive was created and try again.'
                    );
                }

                $decryptedPath = $extractDir.DIRECTORY_SEPARATOR.self::DUMP_ENTRY;
                $this->cipher->decryptFile($dumpPath, $decryptedPath, $passphrase, $manifest['encryption'] ?? []);
                $dumpPath = $decryptedPath;
            }

            $result = $this->runPg('pg_restore', ['--clean', '--if-exists', '--no-owner', '--role='.config('database.connections.pgsql.username'), $dumpPath]);

            if ($result->failed()) {
                $stderr = trim($result->errorOutput() ?: $result->output());

                // pg_restore runs in continue-on-error mode by default: it
                // restores everything it can and then exits non-zero with
                // "errors ignored on restore: N" for the statements it had to
                // skip — almost always DROPs of objects that did not exist yet
                // under --clean. That is not a failed restore; the data is in.
                // Surface the count rather than blowing up. Anything else
                // (connection refused, unreadable archive, auth) is fatal.
                if (preg_match('/errors ignored on restore:\s*(\d+)/i', $stderr, $matches)) {
                    return (int) $matches[1];
                }

                throw new RuntimeException('pg_restore failed: '.$stderr);
            }

            return 0;
        } finally {
            File::deleteDirectory($extractDir);
        }
    }

    public function directory(): string
    {
        return storage_path('app/private/'.self::DIRECTORY);
    }

    private function ensureDirectory(): void
    {
        File::ensureDirectoryExists($this->directory());
    }

    /**
     * Run a Postgres client binary against the configured connection and
     * hand the raw result back to the caller, which decides what a failure
     * means (pg_dump: any error is fatal; pg_restore: "errors ignored on
     * restore" is tolerated — see restore()).
     *
     * The target database is passed with `-d` rather than as a trailing
     * positional argument. pg_dump treats a final positional as the
     * database name, but pg_restore treats its single positional as the
     * *input archive* — appending the database name there makes
     * pg_restore choke with "too many command-line arguments". `-d`
     * works for both, so the dump file can stay positional for restore.
     */
    private function runPg(string $binary, array $extraArgs): ProcessResult
    {
        $config = config('database.connections.pgsql');

        $args = [
            $binary,
            '-h', (string) $config['host'],
            '-p', (string) $config['port'],
            '-U', (string) $config['username'],
            '-d', (string) $config['database'],
            ...$extraArgs,
        ];

        return Process::env(['PGPASSWORD' => (string) $config['password']])
            ->timeout(600)
            ->run($args);
    }

    /** Defend the filesystem path against traversal — we only ever deal with our own filenames. */
    private function safeFilename(string $filename): string
    {
        $filename = basename($filename);

        if ($filename === '' || ! str_ends_with($filename, '.zip')) {
            throw new RuntimeException('Invalid backup filename.');
        }

        return $filename;
    }
}
