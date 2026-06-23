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
     *   external_id?: ?string,
     *   client_name?: ?string,
     *   campaign_name?: ?string,
     *   full_name?: ?string,
     *   email?: ?string,
     *   phone?: ?string,
     *   message?: ?string,
     *   raw_payload?: array<string, mixed>|null,
     *   status?: LeadStatus|string|null,
     *   priority?: LeadPriority|string|null,
     *   meta_lead_id?: ?string,
     *   ad_id?: ?string,
     *   ad_name?: ?string,
     *   adset_id?: ?string,
     *   adset_name?: ?string,
     *   campaign_id?: ?string,
     *   form_id?: ?string,
     *   form_name?: ?string,
     *   platform?: ?string,
     *   is_organic?: ?bool,
     *   status?: LeadStatus|string|null,
     *   priority?: LeadPriority|string|null,
     *   is_qualified?: ?bool,
     *   is_called?: ?bool,
     *   is_mailed?: ?bool,
     *   custom_answers?: array<string, mixed>|null,
     * }  $payload
     */
    public function ingest(array $payload, ?Import $import = null, ?int $tenantId = null, ?int $actorId = null): Lead
    {
        $tenantId ??= $import?->tenant_id ?? Tenant::DEFAULT_ID;

        // Idempotency for recurring sources: if this exact row has already been
        // ingested (same tenant + source + external_id) return the existing lead
        // unchanged. The caller detects this via $lead->wasRecentlyCreated.
        $externalId = isset($payload['external_id']) ? (string) $payload['external_id'] : '';
        if ($externalId !== '') {
            $existingId = $this->duplicates->findByExternalId($tenantId, $payload['source'], $externalId);
            if ($existingId !== null) {
                return Lead::findOrFail($existingId);
            }
        }

        $emailNormalized = $this->normalizer->normalizeEmail($payload['email'] ?? null);
        $phoneNormalized = $this->normalizer->normalizePhone($payload['phone'] ?? null);

        $existingMatchId = $this->duplicates->findMatch($tenantId, $emailNormalized, $phoneNormalized);

        $retentionDays = config('lodgely.compliance.default_retention_days');
        $retentionUntil = $retentionDays ? now()->addDays((int) $retentionDays) : null;

        // Status / priority can arrive as arbitrary strings from external
        // sources (a Google Sheet "Status" column might hold "CREATED", a CRM
        // export "OPEN", etc.). Coerce to a known enum value case-insensitively
        // and fall back to the default rather than letting an unrecognized value
        // blow up the enum cast on save — the raw value is still kept in
        // raw_payload for audit. This is the single chokepoint every importer
        // passes through, so it protects CSV, Meta, Manual and Sheets alike.
        $status = $this->coerceStatus($payload['status'] ?? null);
        $priority = $this->coercePriority($payload['priority'] ?? null);

        return DB::transaction(function () use (
            $payload, $import, $tenantId, $emailNormalized, $phoneNormalized,
            $existingMatchId, $retentionUntil, $status, $priority, $actorId
        ) {
            $lead = Lead::create([
                'tenant_id'         => $tenantId,
                'import_id'         => $import?->id,
                'source'            => $payload['source'],
                'external_id'       => $payload['external_id'] ?? null,
                'client_name'       => $this->normalizer->normalizeText($payload['client_name'] ?? null),
                'campaign_name'     => $this->normalizer->normalizeText($payload['campaign_name'] ?? null),
                'full_name'         => $this->normalizer->normalizeText($payload['full_name'] ?? null),
                'email'             => $this->normalizer->normalizeText($payload['email'] ?? null),
                'phone'             => $this->normalizer->normalizeText($payload['phone'] ?? null),
                'email_normalized'  => $emailNormalized,
                'phone_normalized'  => $phoneNormalized,
                'message'           => $payload['message'] ?? null,
                'raw_payload'       => $payload['raw_payload'] ?? null,
                'meta_lead_id'      => $payload['meta_lead_id'] ?? null,
                'ad_id'             => $payload['ad_id'] ?? null,
                'ad_name'           => $payload['ad_name'] ?? null,
                'adset_id'          => $payload['adset_id'] ?? null,
                'adset_name'        => $payload['adset_name'] ?? null,
                'campaign_id'       => $payload['campaign_id'] ?? null,
                'form_id'           => $payload['form_id'] ?? null,
                'form_name'         => $payload['form_name'] ?? null,
                'platform'          => $payload['platform'] ?? null,
                'is_organic'        => $payload['is_organic'] ?? null,
                'status'            => $existingMatchId ? LeadStatus::Duplicate->value : $status,
                'priority'          => $priority,
                'duplicate_flag'    => $existingMatchId !== null,
                'duplicate_of_id'   => $existingMatchId,
                'retention_until'   => $retentionUntil,
                'qualified_at'      => ($payload['is_qualified'] ?? false) ? now() : null,
                'called_at'         => ($payload['is_called']    ?? false) ? now() : null,
                'mailed_at'         => ($payload['is_mailed']    ?? false) ? now() : null,
                'custom_answers'    => $payload['custom_answers'] ?? null,
            ]);

            $this->audit->record($lead, 'lead.created', [
                'source'        => $lead->source,
                'import_id'     => $import?->id,
                'duplicate_of'  => $existingMatchId,
            ], $actorId);

            return $lead;
        });
    }

    /**
     * Resolve an incoming status to a valid LeadStatus value, defaulting to
     * New for anything unrecognized (case-insensitive). Never throws.
     */
    private function coerceStatus(LeadStatus|string|null $value): string
    {
        if ($value instanceof LeadStatus) {
            return $value->value;
        }

        $normalized = strtolower(trim((string) $value));

        return LeadStatus::tryFrom($normalized)?->value ?? LeadStatus::New->value;
    }

    /**
     * Resolve an incoming priority to a valid LeadPriority value, defaulting to
     * Medium for anything unrecognized (case-insensitive). Never throws.
     */
    private function coercePriority(LeadPriority|string|null $value): string
    {
        if ($value instanceof LeadPriority) {
            return $value->value;
        }

        $normalized = strtolower(trim((string) $value));

        return LeadPriority::tryFrom($normalized)?->value ?? LeadPriority::Medium->value;
    }
}
