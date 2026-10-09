<?php

namespace App\Domain\Ai\Support;

use App\Models\AiSummary;

/**
 * The per-tenant daily cap on completed AI generations
 * (LODGELY_AI_MAX_CALLS_PER_DAY). Shared by every kind — manual summaries,
 * lead qualification and the automatic lead ranker all count against the
 * same budget, so a batch of rankings can never starve an operator's
 * report summary beyond what the cap allows.
 */
class DailyCap
{
    public function cap(): int
    {
        return max(0, (int) config('lodgely.ai.max_calls_per_day', 100));
    }

    /** Completed generations (rows with a response) for this tenant today. */
    public function usedToday(int $tenantId): int
    {
        return AiSummary::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', now()->toDateString())
            ->whereNotNull('response')
            ->count();
    }

    /** Calls still allowed today, or null when the cap is disabled (0). */
    public function remaining(int $tenantId): ?int
    {
        $cap = $this->cap();
        if ($cap === 0) {
            return null;
        }

        return max(0, $cap - $this->usedToday($tenantId));
    }

    public function reached(int $tenantId): bool
    {
        $remaining = $this->remaining($tenantId);

        return $remaining !== null && $remaining <= 0;
    }
}
