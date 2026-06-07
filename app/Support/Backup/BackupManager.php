<?php

namespace App\Support\Backup;

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
 */
class BackupManager
{
    private const DIRECTORY = 'backups';

    private const MANIFEST_ENTRY = 'manifest.json';

    private const DUMP_ENTRY = 'database.dump';

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
            $this->runPg('pg_dump', ['-Fc', '-f', $dumpPath]);

            $manifest = [
                'app' => 'lodgely',
                'app_version' => (string) config('lodgely.version'),
                'db_driver' => 'pgsql',
                'created_at' => now()->toIso8601String(),
            ];

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Could not create backup archive at {$zipPath}.");
            }
            $zip->addFromString(self::MANIFEST_ENTRY, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $zip->addFile($dumpPath, self::DUMP_ENTRY);
            $zip->close();
        } finally {
            @unlink($dumpPath);
        }

        return [
            'filename' => $filename,
            'path' => $zipPath,
            'size' => filesize($zipPath) ?: 0,
            'created_at' => now()->toIso8601String(),
        ];
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
            ->sortByDesc('created_at')
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
     */
    public function restore(string $archivePath): void
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

            if ($zip->locateName(self::MANIFEST_ENTRY) === false || $zip->locateName(self::DUMP_ENTRY) === false) {
                $zip->close();
                throw new RuntimeException('This file does not look like a lodgely backup archive (missing manifest or database dump).');
            }

            $zip->extractTo($extractDir, [self::MANIFEST_ENTRY, self::DUMP_ENTRY]);
            $zip->close();

            $manifestJson = File::get($extractDir.DIRECTORY_SEPARATOR.self::MANIFEST_ENTRY);
            $manifest = json_decode($manifestJson, true);

            if (! is_array($manifest) || ($manifest['app'] ?? null) !== 'lodgely') {
                throw new RuntimeException('This archive does not carry a valid lodgely backup manifest.');
            }

            $dumpPath = $extractDir.DIRECTORY_SEPARATOR.self::DUMP_ENTRY;

            $this->runPg('pg_restore', ['--clean', '--if-exists', '--no-owner', '--role='.config('database.connections.pgsql.username'), $dumpPath]);
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

    /** Run a Postgres client binary against the configured connection. */
    private function runPg(string $binary, array $extraArgs): void
    {
        $config = config('database.connections.pgsql');

        $args = [
            $binary,
            '-h', (string) $config['host'],
            '-p', (string) $config['port'],
            '-U', (string) $config['username'],
            ...$extraArgs,
            (string) $config['database'],
        ];

        $result = Process::env(['PGPASSWORD' => (string) $config['password']])
            ->timeout(600)
            ->run($args);

        if ($result->failed()) {
            throw new RuntimeException("{$binary} failed: ".trim($result->errorOutput() ?: $result->output()));
        }
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
