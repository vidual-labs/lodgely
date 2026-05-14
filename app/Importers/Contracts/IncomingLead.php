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
        ];
    }
}
