<?php

namespace App\Domain\Leads\Enums;

enum UserRole: string
{
    case Operator = 'operator';
    case Client   = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Operator => 'Operator',
            self::Client   => 'Client',
        };
    }
}
