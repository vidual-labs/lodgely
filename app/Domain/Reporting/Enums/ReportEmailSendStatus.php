<?php

namespace App\Domain\Reporting\Enums;

enum ReportEmailSendStatus: string
{
    case Queued = 'queued';
    case Sent   = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('Queued'),
            self::Sent   => __('Sent'),
            self::Failed => __('Failed'),
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Queued => 'bg-amber-50 text-amber-800 ring-amber-600/20',
            self::Sent   => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
            self::Failed => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        };
    }
}
