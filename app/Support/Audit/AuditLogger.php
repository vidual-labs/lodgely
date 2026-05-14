<?php

namespace App\Support\Audit;

use App\Models\Lead;
use App\Models\LeadEvent;
use Illuminate\Support\Facades\Auth;

/**
 * Thin wrapper that writes to the lead_events table. Centralized so we can
 * later swap in async writing, redaction, or external sinks without touching
 * call sites.
 */
class AuditLogger
{
    /** @param  array<string, mixed>  $payload */
    public function record(Lead $lead, string $type, array $payload = [], ?int $actorId = null): LeadEvent
    {
        return LeadEvent::create([
            'lead_id'    => $lead->id,
            'user_id'    => $actorId ?? Auth::id(),
            'type'       => $type,
            'payload'    => $payload,
            'created_at' => now(),
        ]);
    }
}
