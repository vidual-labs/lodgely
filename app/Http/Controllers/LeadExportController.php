<?php

namespace App\Http\Controllers;

use App\Domain\Leads\Services\LeadFilter;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadExportController extends Controller
{
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
        'duplicate_flag',
        'duplicate_of_id',
        'ad_name',
        'adset_name',
        'campaign_id',
        'form_name',
        'platform',
        'is_organic',
    ];

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

            if ($format === 'csv') {
                $csv = Writer::createFromStream($handle);
                $csv->insertOne(self::COLUMNS);
                foreach ($query->lazy(500) as $lead) {
                    $csv->insertOne(array_map([$this, 'escapeCsvFormula'], array_values($this->row($lead, $visibleOriginals))));
                    $count++;
                }
            } else {
                foreach ($query->lazy(500) as $lead) {
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
     * @param  array<int, int>|null  $visibleOriginals  lead ids the user may see as a duplicate's original (null: all)
     * @return array<string, mixed>
     */
    private function row(Lead $lead, ?array $visibleOriginals = null): array
    {
        $out = [];
        foreach (self::COLUMNS as $col) {
            $value = match ($col) {
                'created_at' => $lead->created_at?->toIso8601String(),
                'status' => $lead->status?->value,
                'priority' => $lead->priority?->value,
                'duplicate_flag' => (bool) $lead->duplicate_flag,
                'duplicate_of_id' => $visibleOriginals === null || isset($visibleOriginals[$lead->duplicate_of_id])
                    ? $lead->duplicate_of_id
                    : null,
                'is_organic' => $lead->is_organic === null ? null : (bool) $lead->is_organic,
                default => $lead->{$col},
            };
            $out[$col] = $value;
        }

        return $out;
    }
}
