<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupManager;
use Illuminate\Console\Command;

class CreateBackup extends Command
{
    protected $signature = 'lodgely:backup:create
        {--keep= : Delete older backups beyond this count after a successful run}';

    protected $description = 'Dump the database to a downloadable backup archive under storage/app/private/backups.';

    public function handle(BackupManager $manager): int
    {
        $this->info('Creating backup…');

        $backup = $manager->create();

        $this->info("Backup created: {$backup['filename']} (".number_format($backup['size'] / 1024 / 1024, 2).' MB)');

        // Delegate to the manager rather than re-implementing the walk: it
        // knows to exclude the archive we just wrote, which matters because
        // archive filenames are second-resolution and two backups taken in the
        // same second sort ambiguously. --keep overrides the configured
        // LODGELY_BACKUP_KEEP for this run; create() has already applied the
        // configured value, and pruning twice is a no-op.
        $keep = $this->option('keep');

        if ($keep !== null && is_numeric($keep)) {
            foreach ($manager->prune((int) $keep, $backup['filename']) as $stale) {
                $this->line("Pruned old backup: {$stale}");
            }
        }

        return self::SUCCESS;
    }
}
