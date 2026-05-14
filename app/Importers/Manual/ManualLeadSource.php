<?php

namespace App\Importers\Manual;

use App\Importers\Contracts\IncomingLead;
use App\Importers\Contracts\LeadSource;
use App\Models\Import;

/**
 * Placeholder source for leads created via the in-app form. It does not
 * pull anything — the Livewire form builds an IncomingLead directly and
 * hands it to the LeadIngestor with this source key.
 */
class ManualLeadSource implements LeadSource
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Manual entry';
    }

    public function pull(Import $import): iterable
    {
        return [];
    }
}
