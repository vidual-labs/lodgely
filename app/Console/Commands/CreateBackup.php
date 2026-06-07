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

        $keep = $this->option('keep');
        if ($keep !== null && is_numeric($keep)) {
            $keep = max(1, (int) $keep);
            $existing = $manager->list();

            foreach (array_slice($existing, $keep) as $stale) {
                $manager->delete($stale['filename']);
                $this->line("Pruned old backup: {$stale['filename']}");
            }
        }

        return self::SUCCESS;
    }
}
