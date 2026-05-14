<?php

namespace App\Domain\Leads\Services;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Models\Import;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Tenant;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for "a new lead enters the system, from anywhere".
 *
 * Importers (CSV, EmailMock, Manual, future webhooks) all build a normalized
 * payload and hand it to ingest(); the ingestor takes care of normalization,
 * retention defaults, duplicate flagging and emitting the audit event.
 */
class LeadIngestor
{
    public function __construct(
        private readonly LeadNormalizer $normalizer,
        private readonly DuplicateDetector $duplicates,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *   source: string,
     *   client_name?: ?string,
     *   campaign_name?: ?string,
     *   full_name?: ?string,
     *   email?: ?string,
     *   phone?: ?string,
     *   message?: ?string,
     *   raw_payload?: array<string, mixed>|null,
     *   status?: LeadStatus|string|null,
     *   priority?: LeadPriority|string|null,
     * }  $payload
     */
    public function ingest(array $payload, ?Import $import = null, ?int $tenantId = null, ?int $actorId = null): Lead
    {
        $tenantId ??= $import?->tenant_id ?? Tenant::DEFAULT_ID;

        $emailNormalized = $this->normalizer->normalizeEmail($payload['email'] ?? null);
        $phoneNormalized = $this->normalizer->normalizePhone($payload['phone'] ?? null);

        $existingMatchId = $this->duplicates->findMatch($tenantId, $emailNormalized, $phoneNormalized);

        $retentionDays = config('lodgely.compliance.default_retention_days');
        $retentionUntil = $retentionDays ? now()->addDays((int) $retentionDays) : null;

        $status = $payload['status'] ?? LeadStatus::New;
        if ($status instanceof LeadStatus) {
            $status = $status->value;
        }
        $priority = $payload['priority'] ?? LeadPriority::Medium;
        if ($priority instanceof LeadPriority) {
            $priority = $priority->value;
        }

        return DB::transaction(function () use (
            $payload, $import, $tenantId, $emailNormalized, $phoneNormalized,
            $existingMatchId, $retentionUntil, $status, $priority, $actorId
        ) {
            $lead = Lead::create([
                'tenant_id'         => $tenantId,
                'import_id'         => $import?->id,
                'source'            => $payload['source'],
                'client_name'       => $this->normalizer->normalizeText($payload['client_name'] ?? null),
                'campaign_name'     => $this->normalizer->normalizeText($payload['campaign_name'] ?? null),
                'full_name'         => $this->normalizer->normalizeText($payload['full_name'] ?? null),
                'email'             => $this->normalizer->normalizeText($payload['email'] ?? null),
                'phone'             => $this->normalizer->normalizeText($payload['phone'] ?? null),
                'email_normalized'  => $emailNormalized,
                'phone_normalized'  => $phoneNormalized,
                'message'           => $payload['message'] ?? null,
                'raw_payload'       => $payload['raw_payload'] ?? null,
                'status'            => $existingMatchId ? LeadStatus::Duplicate->value : $status,
                'priority'          => $priority,
                'duplicate_flag'    => $existingMatchId !== null,
                'duplicate_of_id'   => $existingMatchId,
                'retention_until'   => $retentionUntil,
            ]);

            $this->audit->record($lead, 'lead.created', [
                'source'        => $lead->source,
                'import_id'     => $import?->id,
                'duplicate_of'  => $existingMatchId,
            ], $actorId);

            return $lead;
        });
    }
}
