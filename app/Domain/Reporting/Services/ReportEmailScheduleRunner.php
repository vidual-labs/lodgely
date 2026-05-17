<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Enums\ReportEmailCadence;
use App\Models\ClientReportEmailSchedule;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Finds schedules that are due and hands them to the dispatcher, then
 * advances `next_run_at` for recurring entries (or deactivates one-off
 * entries so they don't re-fire). Time math is done in the schedule's
 * stored timezone so weekly/monthly cadences land at the hour the
 * operator actually intended.
 */
class ReportEmailScheduleRunner
{
    public function __construct(private ReportEmailDispatcher $dispatcher) {}

    /** @return int  number of schedules actioned (dispatched OR advanced after empty-recipient skip) */
    public function runDue(?Carbon $now = null): int
    {
        $now ??= now();

        $due = ClientReportEmailSchedule::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->with('email.recipients')
            ->get();

        $count = 0;

        foreach ($due as $schedule) {
            $this->dispatcher->dispatchSchedule($schedule);

            $schedule->last_run_at = $now;

            if ($schedule->cadence === ReportEmailCadence::OneOff) {
                $schedule->is_active   = false;
                $schedule->next_run_at = null;
            } else {
                $schedule->next_run_at = $this->computeNextRunAt($schedule, $now);
            }

            $schedule->save();
            $count++;
        }

        return $count;
    }

    /** Public so unit tests can pin `now()` and exercise cadence math directly. */
    public function computeNextRunAt(ClientReportEmailSchedule $schedule, ?Carbon $now = null): ?Carbon
    {
        $now ??= now();
        $tz   = $schedule->timezone ?: 'UTC';

        return match ($schedule->cadence) {
            ReportEmailCadence::OneOff  => null,
            ReportEmailCadence::Weekly  => $this->nextWeekly($schedule, $now, $tz),
            ReportEmailCadence::Monthly => $this->nextMonthly($schedule, $now, $tz),
        };
    }

    private function nextWeekly(ClientReportEmailSchedule $schedule, Carbon $now, string $tz): Carbon
    {
        $dow  = max(0, min(6, (int) ($schedule->day_of_week ?? 0)));
        $hour = max(0, min(23, (int) ($schedule->hour ?? 0)));

        $localNow = CarbonImmutable::instance($now)->setTimezone($tz);

        $candidate = $localNow->startOfDay()->setHour($hour);

        while ((int) $candidate->dayOfWeek !== $dow || $candidate->lte($localNow)) {
            $candidate = $candidate->addDay();
        }

        return Carbon::instance($candidate)->setTimezone('UTC');
    }

    private function nextMonthly(ClientReportEmailSchedule $schedule, Carbon $now, string $tz): Carbon
    {
        $dom  = max(1, min(28, (int) ($schedule->day_of_month ?? 1)));
        $hour = max(0, min(23, (int) ($schedule->hour ?? 0)));

        $localNow = CarbonImmutable::instance($now)->setTimezone($tz);

        $candidate = $localNow->startOfMonth()->setHour($hour)->day($dom);

        if ($candidate->lte($localNow)) {
            $candidate = $candidate->addMonthNoOverflow()->day($dom)->setHour($hour);
        }

        return Carbon::instance($candidate)->setTimezone('UTC');
    }
}
