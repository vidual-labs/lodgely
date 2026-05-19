<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;

class ImportMetaMock extends Command
{
    protected $signature = 'lodgely:import:meta-mock
        {--count=6 : Number of Meta lead rows to generate per client}
        {--client=* : One or more client_name values to attribute the leads to}';

    protected $description = 'Generate simulated Meta Lead Ads leads (with ad/adset/form attribution). Demo data only — uses fakerphp, dev installs only.';

    public function handle(): int
    {
        $count = (int) $this->option('count');
        if ($count < 1) {
            $this->error('--count must be at least 1.');

            return self::FAILURE;
        }

        $clients = (array) $this->option('client');
        if ($clients === []) {
            // Fall back to whichever clients already exist in the DB so
            // demo logins land on a populated inbox without extra flags.
            $clients = Lead::query()
                ->whereNotNull('client_name')
                ->distinct()
                ->orderBy('client_name')
                ->pluck('client_name')
                ->all();

            if ($clients === []) {
                $this->error('No client_name values found. Pass --client="Northwind Studio" (repeatable) to attribute the leads.');

                return self::FAILURE;
            }

            $this->line('No --client given; spreading leads across: '.implode(', ', $clients));
        }

        $total = 0;
        foreach ($clients as $client) {
            Lead::factory()->count($count)->meta()->create(['client_name' => $client]);
            $this->info("  · {$count} Meta leads created for \"{$client}\"");
            $total += $count;
        }

        $this->info("Done. Created {$total} Meta lead(s).");

        return self::SUCCESS;
    }
}
