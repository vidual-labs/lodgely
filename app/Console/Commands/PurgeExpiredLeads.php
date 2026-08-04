<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;

class PurgeExpiredLeads extends Command
{
    protected $signature = 'lodgely:leads:purge
        {--dry-run : Show what would be deleted without writing}';

    protected $description = 'GDPR-friendly cleanup pass: soft-deletes leads past their retention_until.';

    public function handle(): int
    {
        $query = Lead::query()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<', now())
            ->whereNull('deleted_at');

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->info("Would soft-delete {$count} expired lead(s).");

            return self::SUCCESS;
        }

        // chunkById(), not each()/chunk(): soft-deleting a lead removes it from
        // this query's own result set, so offset-based chunking would shift the
        // remaining rows up a page and silently skip every second chunk —
        // leaving expired leads on disk past their retention date. Keyset
        // pagination on the primary key is immune to that.
        $deleted = 0;
        $query->chunkById(500, function ($leads) use (&$deleted) {
            foreach ($leads as $lead) {
                $lead->delete();
                $deleted++;
            }
        });

        $this->info("Soft-deleted {$deleted} expired lead(s).");

        return self::SUCCESS;
    }
}
