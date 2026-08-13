<?php

namespace App\Domain\Leads\Enums;

/**
 * Where a lead stands.
 *
 * Two groups, deliberately: **intake** states describe the lead as it arrived
 * (is it usable, is it a duplicate, has anyone looked at it), **outcome**
 * states describe what came back after the client actually reached out. The
 * outcome half is what clients live in day to day — they call or mail a lead,
 * send an offer, and then it either lands, gets declined, or goes quiet.
 *
 * Keeping both halves in one enum (rather than adding a second "outcome"
 * column) means the existing status filter, saved views, bulk edit, sorting
 * and CSV export all pick the new states up for free. It is *not* a pipeline:
 * nothing enforces an order and any status can be set at any time — the one
 * exception is the narrow, forward-only nudging in
 * {@see \App\Domain\Leads\Services\LeadStatusAutomation}, which fills in the
 * first two steps (opened → Reviewed, first outreach → Pending) so a client
 * never has to think about status before they have an actual outcome to
 * record.
 */
enum LeadStatus: string
{
    // --- intake -----------------------------------------------------------
    case New        = 'new';
    case Reviewed   = 'reviewed';
    case Incomplete = 'incomplete';
    case Duplicate  = 'duplicate';

    // --- outcome ----------------------------------------------------------
    case Pending    = 'pending';
    case OfferSent  = 'offer_sent';
    case Successful = 'successful';
    case Declined   = 'declined';
    case NoReply    = 'no_reply';
    case Forwarded  = 'forwarded';

    public const GROUP_INTAKE = 'intake';

    public const GROUP_OUTCOME = 'outcome';

    public function label(): string
    {
        return match ($this) {
            self::New        => __('New'),
            self::Reviewed   => __('Reviewed'),
            self::Incomplete => __('Incomplete'),
            self::Duplicate  => __('Duplicate'),
            self::Pending    => __('Pending'),
            self::OfferSent  => __('Offer sent'),
            self::Successful => __('Successful'),
            self::Declined   => __('Declined'),
            self::NoReply    => __('No reply'),
            self::Forwarded  => __('Forwarded'),
        };
    }

    /** Which half of the status list this belongs to — see the class docblock. */
    public function group(): string
    {
        return match ($this) {
            self::New, self::Reviewed, self::Incomplete, self::Duplicate => self::GROUP_INTAKE,
            default => self::GROUP_OUTCOME,
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
            self::Pending    => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            self::OfferSent  => 'bg-violet-50 text-violet-700 ring-violet-600/20',
            self::Successful => 'bg-emerald-100 text-emerald-900 ring-emerald-700/30',
            self::Declined   => 'bg-rose-100 text-rose-900 ring-rose-700/30',
            self::NoReply    => 'bg-slate-200 text-slate-600 ring-slate-500/30',
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

    /**
     * The same options, split into the intake/outcome halves — used for the
     * `<optgroup>`s on the status selects and the pill rows on the lead panel,
     * so a nine-entry list stays scannable.
     *
     * @return array<int, array{key: string, label: string, options: array<int, array{value: string, label: string, badge: string}>}>
     */
    public static function grouped(): array
    {
        $groups = [
            self::GROUP_INTAKE  => ['key' => self::GROUP_INTAKE,  'label' => __('Intake'),  'options' => []],
            self::GROUP_OUTCOME => ['key' => self::GROUP_OUTCOME, 'label' => __('Outcome'), 'options' => []],
        ];

        foreach (self::cases() as $status) {
            $groups[$status->group()]['options'][] = [
                'value' => $status->value,
                'label' => $status->label(),
                'badge' => $status->badgeClasses(),
            ];
        }

        return array_values($groups);
    }
}
