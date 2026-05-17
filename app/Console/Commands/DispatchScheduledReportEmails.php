<?php

namespace App\Console\Commands;

use App\Domain\Reporting\Services\ReportEmailScheduleRunner;
use App\Models\ClientReportEmailSchedule;
use Illuminate\Console\Command;

class DispatchScheduledReportEmails extends Command
{
    protected $signature = 'lodgely:report-emails:dispatch
        {--dry-run : List schedules that would fire without queuing any sends}';

    protected $description = 'Find due report-email schedules, queue sends, and advance next_run_at.';

    public function handle(ReportEmailScheduleRunner $runner): int
    {
        if ($this->option('dry-run')) {
            $due = ClientReportEmailSchedule::query()
                ->where('is_active', true)
                ->whereNotNull('next_run_at')
                ->where('next_run_at', '<=', now())
                ->with('email')
                ->get();

            if ($due->isEmpty()) {
                $this->info('No due report-email schedules.');
                return self::SUCCESS;
            }

            $this->info("Would dispatch {$due->count()} schedule(s):");
            foreach ($due as $schedule) {
                $name = $schedule->email?->name ?? '(deleted)';
                $this->line("  - #{$schedule->id}  template=\"{$name}\"  cadence={$schedule->cadence->value}  next={$schedule->next_run_at}");
            }
            return self::SUCCESS;
        }

        $count = $runner->runDue();
        $this->info("Dispatched {$count} report-email schedule(s).");

        return self::SUCCESS;
    }
}
