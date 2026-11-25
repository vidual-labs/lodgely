<?php

namespace App\Http\Controllers;

use App\Domain\Leads\Services\LeadFilter;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadExportController extends Controller
{
    /**
     * Every stored lead field that's meaningful outside lodgely. The lead's
     * custom form answers come on top of these, one column per question
     * ({@see self::customQuestions()}). Internal plumbing stays out:
     * raw_payload, the *_normalized dedupe keys, tenant/import ids.
     *
     * The AI ranking fields go to clients too: the lead panel and the inbox's
     * AI-tags column already show them to both roles.
     */
    public const COLUMNS = [
        'id',
        'created_at',
        'source',
        'client_name',
        'campaign_name',
        'full_name',
        'email',
        'phone',
        'message',
        'status',
        'priority',
        'qualified_at',
        'called_at',
        'mailed_at',
        'notes',
        'duplicate_flag',
        'duplicate_of_id',
        'ad_name',
        'adset_name',
        'campaign_id',
        'form_name',
        'platform',
        'is_organic',
        'priority_source',
        'ai_priority',
        'ai_reason',
        'ai_tags',
        'ai_ranked_at',
        'external_id',
        'meta_lead_id',
        'ad_id',
        'adset_id',
        'form_id',
        'retention_until',
    ];

    /**
     * Semicolon plus a UTF-8 BOM: what Excel in a German (or any comma-decimal)
     * locale needs to split columns and render umlauts on a plain double-click.
     * Google Sheets, Numbers and LibreOffice detect both on their own.
     */
    public const CSV_DELIMITER = ';';

    private const UTF8_BOM = "\xEF\xBB\xBF";

    private const FORMATS = ['csv', 'ndjson'];

    /**
     * Both operators and clients may export CSV — {@see Lead::scopeVisibleTo()}
     * (via `visibleTo($user)` below) scopes a client's export to their own
     * leads, the same as the inbox table itself. NDJSON stays operator-only:
     * it's a raw-data/API-style format with no client-facing use case, unlike
     * CSV which is the "download for Excel" request clients actually make.
     */
    public function __invoke(Request $request, LeadFilter $filter): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $format = $request->query('format', 'csv');
        if (! in_array($format, self::FORMATS, true)) {
            abort(422, 'Unsupported format. Use csv or ndjson.');
        }
        if ($format === 'ndjson' && ! $user->isOperator()) {
            abort(403);
        }

        $state = [
            'search' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'priority' => (string) $request->query('priority', ''),
            'source' => (string) $request->query('source', ''),
            'client' => (string) $request->query('client', ''),
            'outreach' => (string) $request->query('outreach', ''),
        ];

        $query = $filter->applySort(
            $filter->apply(Lead::query()->visibleTo($user), $state),
            (string) $request->query('sort', 'created_desc'),
        );

        $timestamp = now()->format('Ymd-Hi');
        $filename = "lodgely-leads-{$timestamp}.".($format === 'csv' ? 'csv' : 'ndjson');
        $mime = $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/x-ndjson';

        // lazy(), not lazyById(): keyset chunking replaces only the id order
        // with "id > last id" and keeps created_at DESC in front of it, so
        // every chunk after the first re-read the newest rows and the oldest
        // were never reached (1,100 leads came out as 999 rows, half of them
        // duplicates). Offset chunking keeps the inbox sort intact, and
        // applySort() always ends on an id tiebreaker, so pages are stable.
        // Duplicate detection matches tenant-wide, so a client's lead can be a
        // duplicate of another client's lead. The lead panel hides that
        // original from the client; the export must not print its id either.
        // Operators see everything, so their export keeps every id.
        $visibleOriginals = $user->isOperator() ? null : array_flip(
            Lead::query()->visibleTo($user)
                ->whereIn('id', (clone $query)->reorder()->whereNotNull('duplicate_of_id')->select('duplicate_of_id'))
                ->pluck('id')
                ->all(),
        );

        $count = 0;
        $stream = function () use ($query, $format, &$count, $visibleOriginals): void {
            $handle = fopen('php://output', 'wb');
            $leads = (clone $query)->with('notes.user')->lazy(500);

            if ($format === 'csv') {
                // The header has to name every custom question before the
                // first row goes out, so collect them in a pass of their own.
                $questions = $this->customQuestions($query);

                fwrite($handle, self::UTF8_BOM);
                $csv = Writer::createFromStream($handle);
                $csv->setDelimiter(self::CSV_DELIMITER);
                $csv->insertOne(array_map([$this, 'escapeCsvFormula'], [
                    ...self::COLUMNS,
                    ...$this->questionHeaders($questions),
                ]));
                foreach ($leads as $lead) {
                    $csv->insertOne(array_map([$this, 'escapeCsvFormula'], $this->csvRow($this->row($lead, $visibleOriginals), $questions)));
                    $count++;
                }
            } else {
                foreach ($leads as $lead) {
                    fwrite($handle, json_encode($this->row($lead, $visibleOriginals), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
                    $count++;
                }
            }

            fclose($handle);
        };

        $response = new StreamedResponse(function () use ($stream, $request, $user, $format, $state, &$count) {
            $stream();
            // The search term is deliberately reduced to a boolean: operators
            // routinely search by a lead's email or phone to find one record,
            // so logging it verbatim copies that person's contact details into
            // the log stream on every export. The remaining filters are
            // closed-vocabulary and safe to keep, and knowing *whether* a
            // search was active is what makes the audit line useful.
            $loggableFilters = array_filter(
                array_diff_key($state, ['search' => null]) + ['sort' => (string) $request->query('sort', 'created_desc')],
                fn ($v) => $v !== '',
            );
            $loggableFilters['search_present'] = $state['search'] !== '';

            Log::info('lead.exported', [
                'user_id' => $user->id,
                'format' => $format,
                'count' => $count,
                'filters' => $loggableFilters,
            ]);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);

        return $response;
    }

    /**
     * Neutralizes CSV formula injection: lead fields are attacker-controlled
     * (webhook/CSV/email intake), and a leading =, +, -, @, tab or CR can make
     * spreadsheet software execute a formula when the export is opened.
     */
    private function escapeCsvFormula(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * Distinct custom-answer questions across every exported lead, in the
     * order they first appear (oldest lead first, so a form's own question
     * order carries over). Same trimming as the inbox column picker.
     *
     * @return list<string>
     */
    private function customQuestions(Builder $query): array
    {
        $questions = [];

        (clone $query)
            ->reorder()
            ->whereNotNull('custom_answers')
            ->select(['id', 'custom_answers'])
            ->lazyById(1000)
            ->each(function (Lead $lead) use (&$questions): void {
                foreach ((array) $lead->custom_answers as $qa) {
                    $q = is_array($qa) ? trim((string) ($qa['question'] ?? '')) : '';
                    if ($q !== '') {
                        $questions[$q] = true;
                    }
                }
            });

        return array_map('strval', array_keys($questions));
    }

    /**
     * A question named like a built-in column ("email", "phone") would make
     * two columns with one header; the custom one gets a suffix.
     *
     * @param  list<string>  $questions
     * @return list<string>
     */
    private function questionHeaders(array $questions): array
    {
        return array_map(
            fn (string $q) => in_array($q, self::COLUMNS, true) ? "{$q} (custom)" : $q,
            $questions,
        );
    }

    /**
     * Flattens {@see self::row()} into CSV cells: lists become text, and each
     * custom question gets its own cell (empty where the lead has no answer).
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $questions
     * @return list<mixed>
     */
    private function csvRow(array $row, array $questions): array
    {
        $cells = [];
        foreach (self::COLUMNS as $col) {
            $cells[] = match ($col) {
                'notes' => implode("\n\n", array_map(
                    fn (array $n) => ($n['created_at'] !== null ? $n['created_at'].' · ' : '').$n['author'].': '.$n['body'],
                    $row['notes'],
                )),
                'ai_tags' => implode(', ', $row['ai_tags']),
                default => $row[$col],
            };
        }
        foreach ($questions as $q) {
            $cells[] = $row['custom_answers'][$q] ?? '';
        }

        return $cells;
    }

    /**
     * @param  array<int, int>|null  $visibleOriginals  lead ids the user may see as a duplicate's original (null: all)
     * @return array<string, mixed>
     */
    private function row(Lead $lead, ?array $visibleOriginals = null): array
    {
        $out = [];
        foreach (self::COLUMNS as $col) {
            $value = match ($col) {
                'created_at', 'qualified_at', 'called_at', 'mailed_at', 'ai_ranked_at', 'retention_until'
                    => $lead->{$col}?->toIso8601String(),
                'status', 'priority', 'priority_source', 'ai_priority' => $lead->{$col}?->value,
                'duplicate_flag' => (bool) $lead->duplicate_flag,
                'duplicate_of_id' => $visibleOriginals === null || isset($visibleOriginals[$lead->duplicate_of_id])
                    ? $lead->duplicate_of_id
                    : null,
                'is_organic' => $lead->is_organic === null ? null : (bool) $lead->is_organic,
                'notes' => $lead->notes->map(fn ($note) => [
                    'created_at' => $note->created_at?->format('Y-m-d H:i'),
                    'author' => $note->user?->name ?? 'system',
                    'body' => (string) $note->body,
                ])->values()->all(),
                'ai_tags' => array_values(array_map('strval', array_filter((array) $lead->ai_tags, 'is_scalar'))),
                default => $lead->{$col},
            };
            $out[$col] = $value;
        }
        $out['custom_answers'] = $this->answersByQuestion($lead);

        return $out;
    }

    /**
     * The lead's custom answers keyed by trimmed question. A question asked
     * twice on one lead keeps both answers in one cell.
     *
     * @return array<string, string>
     */
    private function answersByQuestion(Lead $lead): array
    {
        $answers = [];
        foreach ((array) $lead->custom_answers as $qa) {
            $q = is_array($qa) ? trim((string) ($qa['question'] ?? '')) : '';
            if ($q === '') {
                continue;
            }
            $answer = $qa['answer'] ?? '';
            $answer = match (true) {
                is_array($answer) => implode(', ', array_map('strval', array_filter($answer, 'is_scalar'))),
                is_bool($answer) => $answer ? 'true' : 'false',
                default => (string) $answer,
            };
            $answers[$q] = isset($answers[$q]) ? $answers[$q].' | '.$answer : $answer;
        }

        return $answers;
    }
}
