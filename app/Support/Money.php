<?php

namespace App\Support;

/**
 * Tiny currency formatter shared by the reporting views, client reports and
 * report emails. The app stores every monetary amount as integer cents plus a
 * 3-letter ISO currency code (see ad_spend_reports.currency), so the display
 * layer needs a single place that turns a code into a human symbol — otherwise
 * a Meta account billed in EUR still renders as "$" everywhere.
 */
class Money
{
    /**
     * Symbols for the currencies operators are most likely to bill in. Anything
     * not listed falls back to the ISO code (e.g. "AED 1,234.00"), which is
     * always correct even when we don't have a glyph for it.
     */
    private const SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CHF' => 'CHF ',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'NZD' => 'NZ$',
        'SEK' => 'kr ',
        'NOK' => 'kr ',
        'DKK' => 'kr ',
        'PLN' => 'zł ',
        'CZK' => 'Kč ',
        'HUF' => 'Ft ',
        'RON' => 'lei ',
        'BGN' => 'лв ',
        'BRL' => 'R$',
        'MXN' => 'MX$',
        'INR' => '₹',
        'ZAR' => 'R ',
        'TRY' => '₺',
    ];

    public static function symbol(?string $currency): string
    {
        $code = strtoupper(trim((string) $currency));
        if ($code === '') {
            $code = 'USD';
        }

        return self::SYMBOLS[$code] ?? $code.' ';
    }

    /** Format an integer cent amount in the given currency. */
    public static function fromCents(?int $cents, ?string $currency): string
    {
        return self::amount(((int) $cents) / 100, $currency);
    }

    /** Format an already-scaled decimal amount (e.g. a cost-per-lead) in the given currency. */
    public static function amount(float $amount, ?string $currency, int $decimals = 2): string
    {
        return self::symbol($currency).number_format($amount, $decimals);
    }
}
