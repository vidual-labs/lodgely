<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Models\AiSummary;
use App\Models\ClientReportEmail;
use App\Models\ClientReportingView;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Assembles the data block a report-email view (or in-app preview)
 * needs: resolved period, metric rows + KPI totals, the latest
 * operator-approved AI summary, the rendered intro HTML, and the
 * resolved subject line. The recipient is passed in so any per-client
 * Lead visibility (via `Lead::scopeVisibleTo()` inside the builder) is
 * honored.
 */
class ReportEmailComposer
{
    public function __construct(private ClientViewDataBuilder $builder) {}

    /**
     * @return array{
     *   email: ClientReportEmail,
     *   recipient: User,
     *   period: array{from: string, to: string, label: string},
     *   intro_html: ?string,
     *   columns: array<int, \App\Domain\Reporting\Enums\ReportColumn>,
     *   rows: \Illuminate\Support\Collection,
     *   totals: array<string, mixed>,
     *   ai_summary: ?AiSummary,
     *   subject: string,
     * }
     */
    public function compose(ClientReportEmail $email, User $recipient): array
    {
        $months = max(1, min(24, (int) $email->period_months));
        $to     = now()->endOfDay();
        $from   = now()->subMonths($months - 1)->startOfMonth();

        $period = [
            'from'  => $from->format('Y-m-d'),
            'to'    => $to->format('Y-m-d'),
            'label' => $months === 1
                ? $from->format('F Y')
                : $from->format('M Y').' – '.$to->format('M Y'),
        ];

        $columns = [];
        $rows    = collect();
        $totals  = [];

        if ($email->client_reporting_view_id) {
            /** @var ClientReportingView|null $view */
            $view = $email->reportingView;

            if ($view) {
                $columns = $view->columnEnums();
                $rows    = $this->builder->build(
                    $view,
                    $recipient,
                    (int) $email->tenant_id,
                    $period['from'],
                    $period['to'],
                );
                $totals = $this->builder->totals($rows, $columns);
            }
        }

        $aiSummary = null;
        if ($email->include_ai_summary && $email->client_reporting_view_id) {
            $aiSummary = AiSummary::query()
                ->where('tenant_id', $email->tenant_id)
                ->where('kind', AiSummaryKind::ReportView->value)
                ->where('subject_type', ClientReportingView::class)
                ->where('subject_id', $email->client_reporting_view_id)
                ->whereIn('status', [
                    AiSummaryStatus::Approved->value,
                    AiSummaryStatus::Shared->value,
                ])
                ->latest('approved_at')
                ->first();
        }

        return [
            'email'      => $email,
            'recipient'  => $recipient,
            'period'     => $period,
            'intro_html' => $email->intro_markdown
                ? Str::markdown($email->intro_markdown)
                : null,
            'columns'    => $columns,
            'rows'       => $rows,
            'totals'     => $totals,
            'ai_summary' => $aiSummary,
            'subject'    => $this->renderSubject($email, $recipient, $period['label']),
        ];
    }

    private function renderSubject(ClientReportEmail $email, User $recipient, string $periodLabel): string
    {
        $tpl = $email->subject_template ?: 'Your {{period}} report';

        return strtr($tpl, [
            '{{period}}' => $periodLabel,
            '{{client}}' => $recipient->name ?? '',
            '{{name}}'   => $email->name ?? '',
        ]);
    }
}
