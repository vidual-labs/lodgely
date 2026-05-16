<?php

namespace App\Support\Audit;

use App\Models\AiEvent;
use App\Models\AiSummary;
use Illuminate\Support\Facades\Auth;

/**
 * Sibling of AuditLogger that writes to `ai_events`. Centralized so
 * api keys, bearer tokens, or authorization headers can never end up
 * in a payload by accident — every recorded payload is run through
 * `redact()` first.
 */
class AiAuditLogger
{
    /** @param  array<string, mixed>  $payload */
    public function record(AiSummary $summary, string $type, array $payload = [], ?int $actorId = null): AiEvent
    {
        return AiEvent::create([
            'tenant_id'     => $summary->tenant_id,
            'ai_summary_id' => $summary->id,
            'user_id'       => $actorId ?? Auth::id(),
            'type'          => $type,
            'payload'       => $this->redact($payload),
            'created_at'    => now(),
        ]);
    }

    /** Settings-update events have no AiSummary subject. */
    public function recordSettings(int $tenantId, string $type, array $payload = [], ?int $actorId = null): AiEvent
    {
        return AiEvent::create([
            'tenant_id'     => $tenantId,
            'ai_summary_id' => null,
            'user_id'       => $actorId ?? Auth::id(),
            'type'          => $type,
            'payload'       => $this->redact($payload),
            'created_at'    => now(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function redact(array $payload): array
    {
        $sensitive = '/(api[_-]?key|authorization|bearer|secret|password|token)/i';

        $walk = function (array $arr) use (&$walk, $sensitive): array {
            $out = [];
            foreach ($arr as $key => $value) {
                if (is_string($key) && preg_match($sensitive, $key)) {
                    $out[$key] = '[redacted]';
                    continue;
                }
                $out[$key] = is_array($value) ? $walk($value) : $value;
            }

            return $out;
        };

        return $walk($payload);
    }
}
