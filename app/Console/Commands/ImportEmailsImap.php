<?php

namespace App\Console\Commands;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\Email\ImapLeadSource;
use App\Models\Import;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ImportEmailsImap extends Command
{
    protected $signature = 'lodgely:import:email-imap
        {--client= : Override default client name for this pull}
        {--campaign= : Override default campaign name for this pull}
        {--max= : Maximum unseen messages to process (default: config value)}';

    protected $description = 'Pull unseen emails from the configured IMAP mailbox and ingest them as leads.';

    public function handle(ImportRunner $runner, ImapLeadSource $source): int
    {
        if (empty(config('lodgely.importers.email.imap.host'))) {
            $this->error('IMAP is not configured. Set LODGELY_IMAP_HOST (and related vars) in your .env.');

            return self::FAILURE;
        }

        $meta = array_filter([
            'default_client_name'   => $this->option('client'),
            'default_campaign_name' => $this->option('campaign'),
            'max_messages'          => $this->option('max') ? (int) $this->option('max') : null,
        ]);

        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => $source->key(),
            'label'     => 'IMAP pull · ' . now()->format('Y-m-d H:i'),
            'meta'      => $meta ?: null,
        ]);

        $result = $runner->run($import, $source);

        $this->info("Imported {$result->rows_imported} of {$result->rows_total} email lead(s) ({$result->rows_duplicate} duplicate(s)).");

        return self::SUCCESS;
    }
}
