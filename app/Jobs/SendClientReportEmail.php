<?php

namespace App\Jobs;

use App\Domain\Reporting\Enums\ReportEmailSendStatus;
use App\Domain\Reporting\Services\ReportEmailComposer;
use App\Mail\ClientReportEmailMessage;
use App\Models\ClientReportEmailSend;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Composes and mails the report for every recipient on a single Send
 * row. Failure mode: the Send row is flipped to `failed` with the
 * exception message and the job rethrows so the queue logs the failure.
 * AI summary inclusion is decided fresh by the composer at handle time
 * — if there's no approved summary, the section just doesn't render.
 */
class SendClientReportEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(public int $sendId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ReportEmailComposer $composer): void
    {
        /** @var ClientReportEmailSend|null $send */
        $send = ClientReportEmailSend::with(['email.reportingView'])->find($this->sendId);
        if (! $send) {
            return;
        }

        try {
            $recipientIds = $send->recipient_user_ids ?? [];
            $recipients   = User::whereIn('id', $recipientIds)
                ->where('is_active', true)
                ->get();

            if ($recipients->isEmpty()) {
                $send->forceFill([
                    'status' => ReportEmailSendStatus::Failed->value,
                    'error'  => 'No active recipients at send time.',
                ])->save();
                return;
            }

            $aiSummaryId = null;

            foreach ($recipients as $recipient) {
                $composed = $composer->compose($send->email, $recipient);

                // Capture the (per-template) AI summary id from the first compose so
                // the audit row records which summary actually went out.
                if ($aiSummaryId === null && ($composed['ai_summary'] ?? null)) {
                    $aiSummaryId = $composed['ai_summary']->id;
                }

                Mail::to($recipient->email)
                    ->send(new ClientReportEmailMessage($send, $composed));
            }

            $send->forceFill([
                'status'        => ReportEmailSendStatus::Sent->value,
                'ai_summary_id' => $aiSummaryId,
                'sent_at'       => now(),
            ])->save();
        } catch (Throwable $e) {
            $send->forceFill([
                'status' => ReportEmailSendStatus::Failed->value,
                'error'  => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            throw $e;
        }
    }
}
