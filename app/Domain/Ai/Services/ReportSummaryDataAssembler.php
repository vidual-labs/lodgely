<?php

namespace App\Domain\Ai\Services;

use App\Domain\Reporting\Services\ClientViewDataBuilder;
use App\Models\ClientReportingView;
use App\Models\User;

/**
 * Wraps ClientViewDataBuilder so the AiSummarizer doesn't need to know
 * the shape of monthly rows. Output is a compact array ready to be
 * json_encoded into the prompt's user message.
 *
 * Operates entirely on aggregated metrics — no PII ever passes through.
 */
class ReportSummaryDataAssembler
{
    public function __construct(private ClientViewDataBuilder $builder) {}

    /**
     * @return array{
     *   view: array{name: string, columns: array<int, array{key: string, label: string}>},
     *   period: array{from: string, to: string},
     *   monthly: array<int, array<string, mixed>>,
     *   totals: array<string, mixed>,
     * }
     */
    public function assemble(
        ClientReportingView $view,
        User $user,
        int $tenantId,
        string $from,
        string $to,
    ): array {
        $columns = $view->columnEnums();

        $rows   = $this->builder->build($view, $user, $tenantId, $from, $to);
        $totals = $this->builder->totals($rows, $columns);

        $monthly = $rows->map(function ($row) use ($columns) {
            $out = ['month' => $row->month];
            foreach ($columns as $col) {
                $out[$col->value] = $row->{$col->value} ?? null;
            }

            return $out;
        })->all();

        $totalsOut = [];
        foreach ($columns as $col) {
            $totalsOut[$col->value] = $totals[$col->value] ?? null;
        }

        return [
            'view' => [
                'name'    => $view->name,
                'columns' => array_map(
                    static fn ($c) => ['key' => $c->value, 'label' => $c->label()],
                    $columns,
                ),
            ],
            'period'  => ['from' => $from, 'to' => $to],
            'monthly' => $monthly,
            'totals'  => $totalsOut,
        ];
    }
}
