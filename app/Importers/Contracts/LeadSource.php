<?php

namespace App\Importers\Contracts;

use App\Importers\Contracts\IncomingLead;
use App\Models\Import;

/**
 * A lead source is anything that produces a stream of incoming leads from
 * an external channel. Implementations are responsible only for *fetching
 * and parsing* — they hand normalized IncomingLead structs back to the
 * ingestor. Persistence, duplicate detection and retention are not their
 * concern.
 */
interface LeadSource
{
    /** Short stable key, e.g. "csv", "email_mock", "manual". Matches Lead::source. */
    public function key(): string;

    /** Human-readable label, used in the UI and audit log. */
    public function label(): string;

    /**
     * Pull incoming leads for the given import header.
     *
     * @return iterable<IncomingLead>
     */
    public function pull(Import $import): iterable;
}
