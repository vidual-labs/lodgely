<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Domain\Reporting\Enums\ReportColumn;
use App\Models\AdSpendReport;
use App\Models\AiSummary;
use App\Models\ClientReportEmail;
use App\Models\ClientReportingView;
use App\Models\User;
use Illuminate\Support\Collection;
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
     *   columns: array<int, ReportColumn>,
     *   rows: Collection,
     *   totals: array<string, mixed>,
     *   ai_summary: ?AiSummary,
     *   currency: string,
     *   subject: string,
     * }
     */
    public function compose(ClientReportEmail $email, User $recipient): array
    {
        $months = max(1, min(24, (int) $email->period_months));
        $to = now()->endOfDay();
        $from = now()->subMonths($months - 1)->startOfMonth();

        $period = [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'label' => $months === 1
                ? $from->format('F Y')
                : $from->format('M Y').' – '.$to->format('M Y'),
        ];

        $columns = [];
        $rows = collect();
        $totals = [];

        if ($email->client_reporting_view_id) {
            /** @var ClientReportingView|null $view */
            $view = $email->reportingView;

            if ($view) {
                $columns = $view->columnEnums();
                $rows = $this->builder->build(
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
            'email' => $email,
            'recipient' => $recipient,
            'period' => $period,
            'intro_html' => $email->intro_markdown
                ? $this->sanitizeIntroHtml(Str::markdown($email->intro_markdown))
                : null,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
            'ai_summary' => $aiSummary,
            'currency' => AdSpendReport::dominantCurrency((int) $email->tenant_id),
            'subject' => $this->renderSubject($email, $recipient, $period['label']),
        ];
    }

    /**
     * Markdown link syntax lets an operator author an <a href="..."> with any
     * scheme (e.g. javascript:), and strip_tags() only allows/denies tags, not
     * attribute values, so a malicious href would survive into the client's
     * inbox. Restrict surviving <a> tags to http(s)/mailto before whitelisting.
     */
    private function sanitizeIntroHtml(string $html): string
    {
        $html = (string) preg_replace_callback(
            '/<a\s+[^>]*href=(["\'])(.*?)\1[^>]*>/i',
            function (array $m): string {
                $href = trim($m[2]);

                return preg_match('#^(https?://|mailto:)#i', $href) ? $m[0] : '<a>';
            },
            $html
        );

        return strip_tags($html, '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><blockquote><code><pre>');
    }

    private function renderSubject(ClientReportEmail $email, User $recipient, string $periodLabel): string
    {
        $tpl = $email->subject_template ?: 'Your {{period}} report';

        return strtr($tpl, [
            '{{period}}' => $periodLabel,
            '{{client}}' => $recipient->name ?? '',
            '{{name}}' => $email->name ?? '',
        ]);
    }
}
