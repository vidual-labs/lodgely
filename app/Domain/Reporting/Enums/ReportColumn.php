<?php

namespace App\Domain\Reporting\Enums;

enum ReportColumn: string
{
    case Impressions   = 'impressions';
    case Clicks        = 'clicks';
    case Spend         = 'spend';
    case Reach         = 'reach';
    case PlatformLeads = 'platform_leads';
    case Ctr           = 'ctr';
    case Cpl           = 'cpl';
    case LeadCount     = 'lead_count';
    case NewLeads      = 'new_leads';
    case ReviewedLeads = 'reviewed_leads';

    public function label(): string
    {
        return match ($this) {
            self::Impressions   => __('Impressions'),
            self::Clicks        => __('Clicks'),
            self::Spend         => __('Ad Spend'),
            self::Reach         => __('Reach'),
            self::PlatformLeads => __('Platform Leads'),
            self::Ctr           => __('CTR'),
            self::Cpl           => __('Cost per Lead'),
            self::LeadCount     => __('Leads'),
            self::NewLeads      => __('New Leads'),
            self::ReviewedLeads => __('Reviewed Leads'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Impressions   => __('Total ad impressions from the ad platform.'),
            self::Clicks        => __('Total ad clicks from the ad platform.'),
            self::Spend         => __('Total ad spend in the account currency.'),
            self::Reach         => __('Unique users reached by the ads.'),
            self::PlatformLeads => __('Conversions reported by the ad platform.'),
            self::Ctr           => __('Click-through rate: clicks ÷ impressions × 100.'),
            self::Cpl           => __('Cost per lead: ad spend ÷ platform leads.'),
            self::LeadCount     => __('Total leads ingested into Lodgely.'),
            self::NewLeads      => __('Leads with status "New".'),
            self::ReviewedLeads => __('Leads with status "Reviewed".'),
        };
    }

    /** Whether this metric is sourced from ad_spend_reports (tenant-wide). */
    public function isAdMetric(): bool
    {
        return in_array($this, [
            self::Impressions,
            self::Clicks,
            self::Spend,
            self::Reach,
            self::PlatformLeads,
            self::Ctr,
            self::Cpl,
        ], true);
    }

    /** Whether this metric is sourced from the leads table (client-scoped). */
    public function isLeadMetric(): bool
    {
        return in_array($this, [
            self::LeadCount,
            self::NewLeads,
            self::ReviewedLeads,
        ], true);
    }

    public function format(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return match ($this) {
            self::Spend => '$'.number_format($value / 100, 2),
            self::Ctr   => number_format((float) $value, 2).'%',
            self::Cpl   => '$'.number_format((float) $value, 2),
            default     => number_format((int) $value),
        };
    }

    /** @return array<string, string>  value => label */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
