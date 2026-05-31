<?php

namespace Tests\Unit;

use App\Domain\Reporting\Enums\ReportColumn;
use App\Domain\Reporting\Services\ClientViewDataBuilder;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * totals() is pure PHP (no DB), so we can exercise the CTR/CPL/CPC/CPM/
 * Conv-rate re-derivation directly without the Postgres-only build() SQL.
 */
class ClientViewDataBuilderTotalsTest extends TestCase
{
    private function rows(): Collection
    {
        // Two months of raw values, as mergeByMonth() exposes them when a
        // derived ad metric is selected.
        return collect([
            (object) ['clicks' => 100, 'impressions' => 1000, 'spend_cents' => 5000, 'platform_leads' => 10],
            (object) ['clicks' => 300, 'impressions' => 3000, 'spend_cents' => 15000, 'platform_leads' => 20],
        ]);
        // Sums: clicks=400, impressions=4000, spend=20000c ($200), platform_leads=30
    }

    public function test_derived_totals_are_recomputed_from_period_sums(): void
    {
        $builder = new ClientViewDataBuilder();

        $totals = $builder->totals($this->rows(), [
            ReportColumn::Ctr,
            ReportColumn::Cpl,
            ReportColumn::Cpc,
            ReportColumn::Cpm,
            ReportColumn::ConvRate,
        ]);

        // CTR = 400 / 4000 * 100
        $this->assertEqualsWithDelta(10.0, $totals['ctr'], 0.0001);
        // CPL = 20000 / 30 / 100
        $this->assertEqualsWithDelta(6.6667, $totals['cpl'], 0.001);
        // CPC = 20000 / 400 / 100
        $this->assertEqualsWithDelta(0.5, $totals['cpc'], 0.0001);
        // CPM = 20000 * 10 / 4000 = $50 per 1000 impressions
        $this->assertEqualsWithDelta(50.0, $totals['cpm'], 0.0001);
        // Conv. rate = 30 / 400 * 100
        $this->assertEqualsWithDelta(7.5, $totals['conv_rate'], 0.0001);
    }

    public function test_derived_totals_are_null_when_denominator_is_zero(): void
    {
        $builder = new ClientViewDataBuilder();

        $rows = collect([
            (object) ['clicks' => 0, 'impressions' => 0, 'spend_cents' => 0, 'platform_leads' => 0],
        ]);

        $totals = $builder->totals($rows, [
            ReportColumn::Cpc,
            ReportColumn::Cpm,
            ReportColumn::ConvRate,
        ]);

        $this->assertNull($totals['cpc']);
        $this->assertNull($totals['cpm']);
        $this->assertNull($totals['conv_rate']);
    }
}
