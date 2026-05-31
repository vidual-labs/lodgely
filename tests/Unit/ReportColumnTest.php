<?php

namespace Tests\Unit;

use App\Domain\Reporting\Enums\ReportColumn;
use Tests\TestCase;

class ReportColumnTest extends TestCase
{
    public function test_new_derived_metrics_are_ad_metrics(): void
    {
        $this->assertTrue(ReportColumn::Cpc->isAdMetric());
        $this->assertTrue(ReportColumn::Cpm->isAdMetric());
        $this->assertTrue(ReportColumn::ConvRate->isAdMetric());

        $this->assertFalse(ReportColumn::Cpc->isLeadMetric());
        $this->assertFalse(ReportColumn::Cpm->isLeadMetric());
        $this->assertFalse(ReportColumn::ConvRate->isLeadMetric());
    }

    public function test_format_renders_currency_and_percent(): void
    {
        $this->assertSame('$2.50', ReportColumn::Cpc->format(2.5));
        $this->assertSame('$50.00', ReportColumn::Cpm->format(50));
        $this->assertSame('7.50%', ReportColumn::ConvRate->format(7.5));
    }

    public function test_null_values_render_as_dash(): void
    {
        $this->assertSame('—', ReportColumn::Cpc->format(null));
        $this->assertSame('—', ReportColumn::ConvRate->format(null));
    }

    public function test_options_includes_new_metrics(): void
    {
        $options = ReportColumn::options();

        $this->assertArrayHasKey('cpc', $options);
        $this->assertArrayHasKey('cpm', $options);
        $this->assertArrayHasKey('conv_rate', $options);
    }
}
