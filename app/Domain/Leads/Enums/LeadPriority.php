<?php

namespace App\Domain\Leads\Enums;

enum LeadPriority: string
{
    case Low    = 'low';
    case Medium = 'medium';
    case High   = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low    => 'Low',
            self::Medium => 'Medium',
            self::High   => 'High',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Low    => 'bg-slate-50 text-slate-600 ring-slate-400/20',
            self::Medium => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
            self::High   => 'bg-orange-50 text-orange-800 ring-orange-600/30',
        };
    }

    /** Used for default ordering: high first. */
    public function weight(): int
    {
        return match ($this) {
            self::High   => 3,
            self::Medium => 2,
            self::Low    => 1,
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $p) => ['value' => $p->value, 'label' => $p->label()],
            self::cases()
        );
    }
}
