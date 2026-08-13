<?php

namespace App\Domain\Leads\Enums;

/**
 * One-click note phrases offered above the note box on the lead panel.
 *
 * Clients overwhelmingly reach for notes and the outreach pills, and largely
 * ignore the status dropdown — so the fastest route to a lead whose state is
 * actually readable is to make the note itself one tap, and let the note nudge
 * the matching status rather than the other way round. Snippets that describe
 * an outcome carry a {@see suggestedStatus()}; the panel highlights that status
 * pill for a few seconds after the snippet is inserted. It is a nudge, never an
 * automatic write — the client still confirms by tapping the pill, the same
 * deal as the tel:/mailto: → Called/Mailed nudge.
 *
 * The text is deliberately terse: it lands in the note box as a starting point,
 * and anyone can keep typing after it.
 */
enum LeadNoteSnippet: string
{
    case CalledNoAnswer = 'called_no_answer';
    case CalledSpoke    = 'called_spoke';
    case Mailed         = 'mailed';
    case SentDetails    = 'sent_details';
    case SentOffer      = 'sent_offer';
    case NoReply        = 'no_reply';
    case Declined       = 'declined';
    case Accepted       = 'accepted';

    /** The phrase inserted into the note box (also the chip's own label). */
    public function text(): string
    {
        return match ($this) {
            self::CalledNoAnswer => __('Called them, no answer'),
            self::CalledSpoke    => __('Called them, spoke to them'),
            self::Mailed         => __('Mailed them'),
            self::SentDetails    => __('Sent them the details they asked for'),
            self::SentOffer      => __('Sent offer'),
            self::NoReply        => __('No reply so far'),
            self::Declined       => __('Declined offer'),
            self::Accepted       => __('Accepted offer'),
        };
    }

    /** The status this phrase implies, if any — used for the pill nudge. */
    public function suggestedStatus(): ?LeadStatus
    {
        return match ($this) {
            self::SentOffer => LeadStatus::OfferSent,
            self::NoReply   => LeadStatus::NoReply,
            self::Declined  => LeadStatus::Declined,
            self::Accepted  => LeadStatus::Successful,
            default         => null,
        };
    }

    /** @return array<int, array{value: string, text: string, status: string|null}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $s) => [
                'value'  => $s->value,
                'text'   => $s->text(),
                'status' => $s->suggestedStatus()?->value,
            ],
            self::cases()
        );
    }
}
