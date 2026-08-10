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

    public function __invoke(Request $request, LeadFilter $filter): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user?->isOperator(), 403);

        $format = $request->query('format', 'csv');
        if (! in_array($format, self::FORMATS, true)) {
            abort(422, 'Unsupported format. Use csv or ndjson.');
        }

        $state = [
            'search' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'priority' => (string) $request->query('priority', ''),
            'source' => (string) $request->query('source', ''),
            'client' => (string) $request->query('client', ''),
        ];

        $query = $filter->applySort(
            $filter->apply(Lead::query()->visibleTo($user), $state),
            (string) $request->query('sort', 'created_desc'),
        );

        $timestamp = now()->format('Ymd-Hi');
        $filename = "lodgely-leads-{$timestamp}.".($format === 'csv' ? 'csv' : 'ndjson');
        $mime = $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/x-ndjson';

        $count = 0;
        $stream = function () use ($query, $format, &$count): void {
            $handle = fopen('php://output', 'wb');

            if ($format === 'csv') {
                $csv = Writer::createFromStream($handle);
                $csv->insertOne(self::COLUMNS);
                foreach ($query->lazyById(500) as $lead) {
                    $csv->insertOne(array_map([$this, 'escapeCsvFormula'], array_values($this->row($lead))));
                    $count++;
                }
            } else {
                foreach ($query->lazyById(500) as $lead) {
                    fwrite($handle, json_encode($this->row($lead), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
                    $count++;
                }
            }

            fclose($handle);
        };

        $response = new StreamedResponse(function () use ($stream, $request, $user, $format, $state, &$count) {
            $stream();
            Log::info('lead.exported', [
                'user_id' => $user->id,
                'format' => $format,
                'count' => $count,
                'filters' => array_filter($state + ['sort' => (string) $request->query('sort', 'created_desc')], fn ($v) => $v !== ''),
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

    /** @return array<string, mixed> */
    private function row(Lead $lead): array
    {
        $out = [];
        foreach (self::COLUMNS as $col) {
            $value = match ($col) {
                'created_at' => $lead->created_at?->toIso8601String(),
                'status' => $lead->status?->value,
                'priority' => $lead->priority?->value,
                'duplicate_flag' => (bool) $lead->duplicate_flag,
                'is_organic' => $lead->is_organic === null ? null : (bool) $lead->is_organic,
                default => $lead->{$col},
            };
            $out[$col] = $value;
        }

        return $out;
    }
}
