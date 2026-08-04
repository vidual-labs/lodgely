<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Domain\Reporting\Enums\ReportColumn;
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
                ? $this->renderIntroHtml($email->intro_markdown)
                : null,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
            'ai_summary' => $aiSummary,
            'currency' => $this->builder->dominantCurrencyForUser((int) $email->tenant_id, $recipient),
            'subject' => $this->renderSubject($email, $recipient, $period['label']),
        ];
    }

    /**
     * Tags kept in the rendered intro. Everything CommonMark can emit that we
     * don't want in an email body (images, tables, raw markup) is dropped.
     */
    private const INTRO_ALLOWED_TAGS = '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><blockquote><code><pre>';

    /**
     * Render the operator-authored intro to the HTML that goes, unescaped, into
     * the client's email and the in-app preview (see
     * resources/views/mail/client-report.blade.php).
     *
     * The dangerous input is a link with a script-bearing scheme, or raw HTML
     * carrying an event handler. CommonMark handles both properly and we let it:
     * `allow_unsafe_links` drops javascript:/data:/vbscript: hrefs, and
     * `html_input => strip` means raw HTML in the source never reaches the
     * output at all. strip_tags() then bounds the tag set as defence in depth.
     *
     * This replaced a hand-rolled regex pass over the rendered HTML, which had
     * two holes: it only matched *quoted* href values, so
     * `<a href=javascript:…>` slipped through untouched, and for a link whose
     * scheme did pass it re-emitted the original tag verbatim — carrying any
     * `onclick=` along with it. Both survived strip_tags(), which filters tags
     * but never attributes. Don't reintroduce regex sanitizing here.
     */
    private function renderIntroHtml(string $markdown): string
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return strip_tags($html, self::INTRO_ALLOWED_TAGS);
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
