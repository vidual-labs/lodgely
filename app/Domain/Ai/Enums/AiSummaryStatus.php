<?php

namespace App\Domain\Ai\Enums;

enum AiSummaryStatus: string
{
    case Pending  = 'pending';
    case Failed   = 'failed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Shared   = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => __('Pending review'),
            self::Failed   => __('Failed'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Shared   => __('Shared with client'),
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending  => 'bg-amber-50 text-amber-800 ring-amber-600/20',
            self::Failed   => 'bg-rose-50 text-rose-700 ring-rose-600/20',
            self::Approved => 'bg-blue-50 text-blue-700 ring-blue-600/20',
            self::Rejected => 'bg-slate-100 text-slate-600 ring-slate-500/20',
            self::Shared   => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
        };
    }
}
