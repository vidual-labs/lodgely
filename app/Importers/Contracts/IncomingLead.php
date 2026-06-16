<?php

namespace App\Importers\Contracts;

/**
 * Transport struct between importer adapters and the LeadIngestor. Plain
 * data — no DB awareness. Importers fill this in, the ingestor takes over.
 */
final readonly class IncomingLead
{
    public function __construct(
        public string $source,
        public ?string $clientName = null,
        public ?string $campaignName = null,
        public ?string $fullName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $message = null,
        /** @var array<string, mixed>|null */
        public ?array $rawPayload = null,
        // Stable per-row identity from recurring sources, so re-reading the same
        // source never re-creates a lead. Null for one-shot imports (CSV, manual).
        public ?string $externalId = null,
        // Meta Lead Ads structural fields (stable across all forms)
        public ?string $metaLeadId = null,
        public ?string $adId = null,
        public ?string $adName = null,
        public ?string $adsetId = null,
        public ?string $adsetName = null,
        public ?string $campaignId = null,
        public ?string $formId = null,
        public ?string $formName = null,
        public ?string $platform = null,
        public ?bool $isOrganic = null,
        // Override lead status / priority at import time
        public ?string $status = null,
        public ?string $priority = null,
        // Outreach toggles: truthy value → set the corresponding *_at timestamp
        public ?bool $isQualified = null,
        public ?bool $isCalled = null,
        public ?bool $isMailed = null,
        /** @var array<string, mixed>|null */
        public ?array $customAnswers = null,
    ) {}

    /** @return array<string, mixed> */
    public function toIngestPayload(): array
    {
        return [
            'source'         => $this->source,
            'client_name'    => $this->clientName,
            'campaign_name'  => $this->campaignName,
            'full_name'      => $this->fullName,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'message'        => $this->message,
            'raw_payload'    => $this->rawPayload,
            'external_id'    => $this->externalId,
            'meta_lead_id'   => $this->metaLeadId,
            'ad_id'          => $this->adId,
            'ad_name'        => $this->adName,
            'adset_id'       => $this->adsetId,
            'adset_name'     => $this->adsetName,
            'campaign_id'    => $this->campaignId,
            'form_id'        => $this->formId,
            'form_name'      => $this->formName,
            'platform'        => $this->platform,
            'is_organic'      => $this->isOrganic,
            'status'          => $this->status,
            'priority'        => $this->priority,
            'is_qualified'    => $this->isQualified,
            'is_called'       => $this->isCalled,
            'is_mailed'       => $this->isMailed,
            'custom_answers'  => $this->customAnswers,
        ];
    }
}
