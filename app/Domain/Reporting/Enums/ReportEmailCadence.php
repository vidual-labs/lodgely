<?php

namespace App\Domain\Reporting\Enums;

enum ReportEmailCadence: string
{
    case OneOff  = 'one_off';
    case Weekly  = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::OneOff  => __('One-off'),
            self::Weekly  => __('Weekly'),
            self::Monthly => __('Monthly'),
        };
    }

    public function isRecurring(): bool
    {
        return $this !== self::OneOff;
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases()
        );
    }
}
