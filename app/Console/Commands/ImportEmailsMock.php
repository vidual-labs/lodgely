<?php

namespace App\Console\Commands;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\EmailMock\EmailMockLeadSource;
use App\Models\Import;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ImportEmailsMock extends Command
{
    protected $signature = 'lodgely:import:email-mock
        {--count=3 : Number of mock emails to generate}
        {--client= : Default client name to attribute}';

    protected $description = 'Generate simulated email leads. Useful for demos and scheduled smoke tests.';

    public function handle(ImportRunner $runner, EmailMockLeadSource $source): int
    {
        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => $source->key(),
            'label'     => 'Scheduled mock email pull · '.now()->format('Y-m-d H:i'),
            'meta'      => [
                'count'                 => (int) $this->option('count'),
                'default_client_name'   => $this->option('client'),
                'default_campaign_name' => 'Website contact form',
            ],
        ]);

        $result = $runner->run($import, $source);

        $this->info("Imported {$result->rows_imported} of {$result->rows_total} mock email lead(s).");

        return self::SUCCESS;
    }
}
