<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupManager;
use Illuminate\Console\Command;

class RestoreBackup extends Command
{
    protected $signature = 'lodgely:backup:restore
        {path : Absolute path to a lodgely backup .zip archive}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Restore the database from a backup archive. Destructive — replaces all current data.';

    public function handle(BackupManager $manager): int
    {
        $path = (string) $this->argument('path');

        if (! $this->option('force') && ! $this->confirm(
            "This will DROP and replace every table in the database with the contents of {$path}. Continue?",
            false,
        )) {
            $this->warn('Aborted — no changes made.');
            return self::FAILURE;
        }

        $this->info('Restoring backup… this can take a while for large databases.');

        $manager->restore($path);

        $this->info('Restore complete.');

        return self::SUCCESS;
    }
}
