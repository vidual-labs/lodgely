<?php

namespace App\Domain\Leads\Enums;

use App\Models\User;

/**
 * A client-level preset that swaps the "Lead" noun for the client's own
 * inbox view. Each case's phrase methods return the literal English string
 * used as the `__()` translation key — a full sentence per language, not
 * an interpolated fragment, so word order/pluralization stays correct.
 *
 * Only the client's own view is relabeled ({@see self::current()}); an
 * operator's mixed, multi-client inbox always reads "Lead".
 */
enum ClientType: string
{
    case B2b = 'b2b';
    case Jobs = 'jobs';
    case B2c = 'b2c';
    case IndividualIntent = 'individual_intent';

    public function label(): string
    {
        return match ($this) {
            self::B2b => 'B2B (default)',
            self::Jobs => 'Jobs',
            self::B2c => 'B2C',
            self::IndividualIntent => 'Individual intent',
        };
    }

    public function inboxTitle(): string
    {
        return match ($this) {
            self::B2b => 'Lead inbox',
            self::Jobs => 'Applicant inbox',
            self::B2c => 'Prospect inbox',
            self::IndividualIntent => 'Inquiry inbox',
        };
    }

    public function subtitle(): string
    {
        return match ($this) {
            self::B2b => 'Your leads across all configured sources.',
            self::Jobs => 'Your applicants across all configured sources.',
            self::B2c => 'Your prospects across all configured sources.',
            self::IndividualIntent => 'Your inquiries across all configured sources.',
        };
    }

    public function emptyStateText(): string
    {
        return match ($this) {
            self::B2b => 'No leads match these filters yet.',
            self::Jobs => 'No applicants match these filters yet.',
            self::B2c => 'No prospects match these filters yet.',
            self::IndividualIntent => 'No inquiries match these filters yet.',
        };
    }

    public function detailTitleKey(): string
    {
        return match ($this) {
            self::B2b => 'Lead #:id',
            self::Jobs => 'Applicant #:id',
            self::B2c => 'Prospect #:id',
            self::IndividualIntent => 'Inquiry #:id',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases()
        );
    }

    /** The type driving the current request's inbox copy — B2b unless a client user has one set. */
    public static function current(): self
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->isClient() ? ($user->client_type ?? self::B2b) : self::B2b;
    }
}
