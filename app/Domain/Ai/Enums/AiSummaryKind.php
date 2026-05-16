<?php

namespace App\Domain\Ai\Enums;

enum AiSummaryKind: string
{
    case ReportView        = 'report_view';
    case LeadQualification = 'lead_qualification';

    public function label(): string
    {
        return match ($this) {
            self::ReportView        => __('Report summary'),
            self::LeadQualification => __('Lead qualification'),
        };
    }

    /** Whether the kind operates on lead-level data that needs pseudonymization. */
    public function touchesLeadData(): bool
    {
        return $this === self::LeadQualification;
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $k) => ['value' => $k->value, 'label' => $k->label()],
            self::cases()
        );
    }
}
