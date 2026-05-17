<?php

namespace Tests\Unit;

use App\Domain\Reporting\Enums\ReportEmailCadence;
use App\Domain\Reporting\Services\ReportEmailDispatcher;
use App\Domain\Reporting\Services\ReportEmailScheduleRunner;
use App\Models\ClientReportEmailSchedule;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Pure cadence math — no database, no queue. We feed the runner a
 * schedule model directly and assert that computeNextRunAt() honors
 * day-of-week / day-of-month / hour in the schedule's stored timezone.
 */
class ReportEmailScheduleRunnerTest extends TestCase
{
    private function runner(): ReportEmailScheduleRunner
    {
        return new ReportEmailScheduleRunner($this->createMock(ReportEmailDispatcher::class));
    }

    public function test_weekly_advances_to_next_matching_weekday_at_configured_hour_utc(): void
    {
        // Now is Mon 2026-05-18 12:00 UTC. Schedule fires every Mon at 09:00 UTC.
        // Mon 09:00 has passed, so next run is Mon 2026-05-25 09:00 UTC.
        Carbon::setTestNow(Carbon::parse('2026-05-18 12:00:00', 'UTC'));

        $schedule = new ClientReportEmailSchedule([
            'cadence'     => ReportEmailCadence::Weekly->value,
            'day_of_week' => 1,   // Monday
            'hour'        => 9,
            'timezone'    => 'UTC',
        ]);
        $schedule->cadence = ReportEmailCadence::Weekly;

        $next = $this->runner()->computeNextRunAt($schedule);

        $this->assertSame('2026-05-25 09:00:00', $next?->format('Y-m-d H:i:s'));
    }

    public function test_weekly_advances_to_same_day_later_today_when_hour_is_in_the_future(): void
    {
        // Now is Mon 2026-05-18 06:00 UTC. Mon 09:00 today is still in the future.
        Carbon::setTestNow(Carbon::parse('2026-05-18 06:00:00', 'UTC'));

        $schedule = new ClientReportEmailSchedule([
            'cadence'     => ReportEmailCadence::Weekly->value,
            'day_of_week' => 1,
            'hour'        => 9,
            'timezone'    => 'UTC',
        ]);
        $schedule->cadence = ReportEmailCadence::Weekly;

        $next = $this->runner()->computeNextRunAt($schedule);

        $this->assertSame('2026-05-18 09:00:00', $next?->format('Y-m-d H:i:s'));
    }

    public function test_monthly_advances_to_next_month_if_day_already_passed(): void
    {
        // Now is 2026-05-17 12:00 UTC. Monthly on day 5 → next is 2026-06-05 09:00 UTC.
        Carbon::setTestNow(Carbon::parse('2026-05-17 12:00:00', 'UTC'));

        $schedule = new ClientReportEmailSchedule([
            'cadence'      => ReportEmailCadence::Monthly->value,
            'day_of_month' => 5,
            'hour'         => 9,
            'timezone'     => 'UTC',
        ]);
        $schedule->cadence = ReportEmailCadence::Monthly;

        $next = $this->runner()->computeNextRunAt($schedule);

        $this->assertSame('2026-06-05 09:00:00', $next?->format('Y-m-d H:i:s'));
    }

    public function test_monthly_lands_on_same_month_when_day_is_still_in_the_future(): void
    {
        // Now is 2026-05-03 12:00 UTC. Day 20 is later this month.
        Carbon::setTestNow(Carbon::parse('2026-05-03 12:00:00', 'UTC'));

        $schedule = new ClientReportEmailSchedule([
            'cadence'      => ReportEmailCadence::Monthly->value,
            'day_of_month' => 20,
            'hour'         => 9,
            'timezone'     => 'UTC',
        ]);
        $schedule->cadence = ReportEmailCadence::Monthly;

        $next = $this->runner()->computeNextRunAt($schedule);

        $this->assertSame('2026-05-20 09:00:00', $next?->format('Y-m-d H:i:s'));
    }

    public function test_one_off_returns_null_next_run_at(): void
    {
        $schedule = new ClientReportEmailSchedule([
            'cadence'  => ReportEmailCadence::OneOff->value,
            'hour'     => 9,
            'timezone' => 'UTC',
        ]);
        $schedule->cadence = ReportEmailCadence::OneOff;

        $this->assertNull($this->runner()->computeNextRunAt($schedule));
    }

    public function test_weekly_honors_non_utc_timezone(): void
    {
        // Now is 2026-05-18 23:00 Berlin = 21:00 UTC. Schedule = Mon 09:00 Berlin
        // → today's local 09:00 already passed; next is Mon 2026-05-25 09:00 Berlin
        // → which is 07:00 UTC on 2026-05-25.
        Carbon::setTestNow(Carbon::parse('2026-05-18 21:00:00', 'UTC'));

        $schedule = new ClientReportEmailSchedule([
            'cadence'     => ReportEmailCadence::Weekly->value,
            'day_of_week' => 1,
            'hour'        => 9,
            'timezone'    => 'Europe/Berlin',
        ]);
        $schedule->cadence = ReportEmailCadence::Weekly;

        $next = $this->runner()->computeNextRunAt($schedule);

        $this->assertSame('2026-05-25 07:00:00', $next?->format('Y-m-d H:i:s'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
