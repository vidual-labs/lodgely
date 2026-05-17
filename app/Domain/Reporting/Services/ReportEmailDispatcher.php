<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Enums\ReportEmailSendStatus;
use App\Jobs\SendClientReportEmail;
use App\Models\ClientReportEmail;
use App\Models\ClientReportEmailSchedule;
use App\Models\ClientReportEmailSend;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for actually dispatching a report email. Creates the
 * `client_report_email_sends` audit row inside a transaction, then queues
 * the `SendClientReportEmail` job. Both ad-hoc ("send now" / "send test")
 * and the cron scheduler funnel through here, so the audit shape is
 * identical regardless of trigger.
 */
class ReportEmailDispatcher
{
    /**
     * Manually dispatched send. If `$overrideRecipients` is non-empty it
     * replaces the template's saved recipient list (used by the "Send
     * test to me" action).
     *
     * @param  array<int, User>  $overrideRecipients
     */
    public function dispatchNow(
        ClientReportEmail $email,
        ?User $actor = null,
        array $overrideRecipients = [],
    ): ?ClientReportEmailSend {
        $recipients = $overrideRecipients !== []
            ? $overrideRecipients
            : $email->recipients()->where('is_active', true)->get()->all();

        if ($recipients === []) {
            return null;
        }

        return $this->createSendAndQueue(
            email: $email,
            schedule: null,
            actor: $actor,
            recipients: $recipients,
        );
    }

    /**
     * Cron-driven send for a due schedule. Returns null if the template
     * is inactive or has no eligible recipients (treated as a silent
     * no-op rather than a failure — operators usually want the schedule
     * to just keep trying). Deactivating the template is the operator's
     * "pause" switch and must be honored even when a schedule is left
     * active on the row.
     */
    public function dispatchSchedule(ClientReportEmailSchedule $schedule): ?ClientReportEmailSend
    {
        $email = $schedule->email;

        if ($email === null || ! $email->is_active) {
            return null;
        }

        $recipients = $email->recipients()->where('is_active', true)->get()->all();

        if ($recipients === []) {
            return null;
        }

        return $this->createSendAndQueue(
            email: $email,
            schedule: $schedule,
            actor: null,
            recipients: $recipients,
        );
    }

    /** @param  array<int, User>  $recipients */
    private function createSendAndQueue(
        ClientReportEmail $email,
        ?ClientReportEmailSchedule $schedule,
        ?User $actor,
        array $recipients,
    ): ClientReportEmailSend {
        $months = max(1, min(24, (int) $email->period_months));
        $from   = now()->subMonths($months - 1)->startOfMonth()->format('Y-m-d');
        $to     = now()->endOfDay()->format('Y-m-d');

        $send = DB::transaction(function () use ($email, $schedule, $actor, $recipients, $from, $to) {
            return ClientReportEmailSend::create([
                'tenant_id'              => $email->tenant_id,
                'client_report_email_id' => $email->id,
                'schedule_id'            => $schedule?->id,
                'triggered_by'           => $actor?->id,
                'period_from'            => $from,
                'period_to'              => $to,
                'recipient_user_ids'     => array_values(array_map(fn (User $u) => (int) $u->id, $recipients)),
                'status'                 => ReportEmailSendStatus::Queued->value,
            ]);
        });

        SendClientReportEmail::dispatch($send->id);

        return $send;
    }
}
