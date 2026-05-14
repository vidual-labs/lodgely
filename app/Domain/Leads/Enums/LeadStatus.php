<?php

namespace App\Domain\Leads\Enums;

enum LeadStatus: string
{
    case New        = 'new';
    case Reviewed   = 'reviewed';
    case Incomplete = 'incomplete';
    case Duplicate  = 'duplicate';
    case Forwarded  = 'forwarded';

    public function label(): string
    {
        return match ($this) {
            self::New        => 'New',
            self::Reviewed   => 'Reviewed',
            self::Incomplete => 'Incomplete',
            self::Duplicate  => 'Duplicate',
            self::Forwarded  => 'Forwarded',
        };
    }

    /** Tailwind class fragment for the status pill. Muted, B2B palette. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::New        => 'bg-blue-50 text-blue-700 ring-blue-600/20',
            self::Reviewed   => 'bg-slate-100 text-slate-700 ring-slate-500/20',
            self::Incomplete => 'bg-amber-50 text-amber-800 ring-amber-600/20',
            self::Duplicate  => 'bg-rose-50 text-rose-700 ring-rose-600/20',
            self::Forwarded  => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $s) => ['value' => $s->value, 'label' => $s->label()],
            self::cases()
        );
    }
}
