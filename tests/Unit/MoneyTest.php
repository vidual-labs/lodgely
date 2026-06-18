<?php

namespace Tests\Unit;

use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_symbol_maps_known_codes_and_falls_back_to_iso(): void
    {
        $this->assertSame('$', Money::symbol('USD'));
        $this->assertSame('€', Money::symbol('EUR'));
        $this->assertSame('£', Money::symbol('GBP'));
        $this->assertSame('AED ', Money::symbol('AED'));
        // Blank / null defaults to USD so we never render a bare number.
        $this->assertSame('$', Money::symbol(''));
        $this->assertSame('$', Money::symbol(null));
    }

    public function test_from_cents_scales_and_formats(): void
    {
        $this->assertSame('€1,234.56', Money::fromCents(123456, 'EUR'));
        $this->assertSame('$0.00', Money::fromCents(0, 'USD'));
        $this->assertSame('$0.00', Money::fromCents(null, 'USD'));
    }

    public function test_amount_formats_already_scaled_values(): void
    {
        $this->assertSame('€12.50', Money::amount(12.5, 'EUR'));
        $this->assertSame('$12.50', Money::amount(12.5, 'usd'));
    }
}
