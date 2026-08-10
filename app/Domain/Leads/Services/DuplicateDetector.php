<?php

namespace App\Domain\Leads\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\DB;

/**
 * MVP duplicate detection rule:
 *
 *   A lead is a potential duplicate of another (within the same tenant) if
 *   either its normalized email OR its normalized phone matches an existing
 *   lead's. Soft-deleted leads are ignored.
 *
 * The rule is intentionally simple. More signals (fuzzy name match, time
 * windows, source-aware weighting) are easy to layer in here later without
 * changing callers.
 */
class DuplicateDetector
{
    /**
     * Idempotency lookup for recurring sources: returns the id of an existing
     * lead carrying the same stable external_id within this tenant and
     * source, or null. Used so re-reading a source (e.g. a Google Sheet, or an
     * OpenFlow form) recognizes rows it has already ingested instead of
     * creating duplicates.
     *
     * Deliberately includes soft-deleted leads: an operator deleting a lead
     * in the inbox is not "this row was never ingested" — it's a decision to
     * remove it, and the next recurring pull (which re-walks a window of
     * recent submissions, not just ones newer than the last fetch) must not
     * silently resurrect it as a fresh lead.
     */
    public function findByExternalId(int $tenantId, string $source, string $externalId): ?int
    {
        if ($externalId === '') {
            return null;
        }

        return Lead::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('source', $source)
            ->where('external_id', $externalId)
            ->orderBy('id')
            ->value('id');
    }

    /** Returns the id of an existing matching lead, or null. */
    public function findMatch(
        int $tenantId,
        ?string $emailNormalized,
        ?string $phoneNormalized,
        ?int $ignoreLeadId = null,
    ): ?int {
        if ($emailNormalized === null && $phoneNormalized === null) {
            return null;
        }

        $query = Lead::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when($ignoreLeadId, fn ($q) => $q->where('id', '!=', $ignoreLeadId))
            ->where(function ($q) use ($emailNormalized, $phoneNormalized) {
                if ($emailNormalized !== null) {
                    $q->orWhere('email_normalized', $emailNormalized);
                }
                if ($phoneNormalized !== null) {
                    $q->orWhere('phone_normalized', $phoneNormalized);
                }
            })
            ->orderBy('id');

        return $query->value('id');
    }

    /**
     * Re-evaluate one specific lead and update its duplicate_flag / duplicate_of_id
     * columns in-place. Returns true if the flag was changed.
     */
    public function reconcile(Lead $lead): bool
    {
        $match = $this->findMatch(
            $lead->tenant_id,
            $lead->email_normalized,
            $lead->phone_normalized,
            $lead->id,
        );

        $newFlag = $match !== null;
        $changed = $newFlag !== (bool) $lead->duplicate_flag
            || ($lead->duplicate_of_id ?? null) !== $match;

        if ($changed) {
            DB::table('leads')->where('id', $lead->id)->update([
                'duplicate_flag'   => $newFlag,
                'duplicate_of_id'  => $match,
                'updated_at'       => now(),
            ]);
            $lead->duplicate_flag = $newFlag;
            $lead->duplicate_of_id = $match;
        }

        return $changed;
    }
}
