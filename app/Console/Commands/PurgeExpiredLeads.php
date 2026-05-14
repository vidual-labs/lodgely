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

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would soft-delete {$count} expired lead(s).");
            return self::SUCCESS;
        }

        $query->each(function (Lead $lead) {
            $lead->delete();
        });

        $this->info("Soft-deleted {$count} expired lead(s).");

        return self::SUCCESS;
    }
}
