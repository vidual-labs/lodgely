<?php

namespace App\Domain\Leads\Services;

use App\Domain\Leads\Enums\LeadStatus;
use App\Models\Lead;
use App\Support\Audit\AuditLogger;

/**
 * Moves a lead's status along on its own for the two steps nobody wants to
 * click: opening a lead marks it Reviewed, and the first outreach toggle marks
 * it Pending. Everything past that (offer sent, successful, declined, no reply)
 * stays a deliberate act — the app can observe that someone *looked* at a lead
 * or *reached out*, it cannot observe what came back.
 *
 * Two rules keep this from ever destroying information:
 *
 *  - **Forward only, from a known starting point.** Each auto-target lists the
 *    statuses it is allowed to replace ({@see REPLACEABLE}). Reviewed only
 *    replaces New; Pending only replaces New or Reviewed. A lead someone has
 *    already put into Incomplete, Duplicate, Offer sent, Successful, Declined,
 *    No reply or Forwarded is never touched.
 *  - **Audited like any other change.** The same `lead.status_changed` event as
 *    a manual edit, with `automatic: true` in the payload so the trail shows who
 *    (or what) moved it.
 */
class LeadStatusAutomation
{
    /**
     * Which statuses each auto-target may overwrite, keyed by target value.
     *
     * @var array<string, list<string>>
     */
    private const REPLACEABLE = [
        'reviewed' => ['new'],
        'pending'  => ['new', 'reviewed'],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /** A client or operator opened the lead in the side panel. */
    public function markReviewed(Lead $lead): bool
    {
        return $this->advanceTo($lead, LeadStatus::Reviewed);
    }

    /** Someone recorded a first contact (qualified / called / mailed). */
    public function markPending(Lead $lead): bool
    {
        return $this->advanceTo($lead, LeadStatus::Pending);
    }

    /** @return bool Whether the status actually moved. */
    private function advanceTo(Lead $lead, LeadStatus $target): bool
    {
        $current = $lead->status?->value;

        if (! in_array($current, self::REPLACEABLE[$target->value] ?? [], true)) {
            return false;
        }

        $lead->status = $target;
        $lead->save();

        $this->audit->record($lead, 'lead.status_changed', [
            'from'      => $current,
            'to'        => $target->value,
            'automatic' => true,
        ]);

        return true;
    }
}
