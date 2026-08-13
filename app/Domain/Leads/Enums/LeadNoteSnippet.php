<?php

namespace App\Domain\Leads\Enums;

/**
 * One-click note phrases offered above the note box on the lead panel.
 *
 * Clients overwhelmingly reach for notes and the outreach pills, and largely
 * ignore the status dropdown — so the fastest route to a lead whose state is
 * actually readable is to make the note itself one tap.
 *
 * Declined and Successful are already precise, filterable events on their own
 * — {@see LeadStatus} carries them, the audit trail timestamps every change,
 * and the inbox can filter and bulk-edit on them. A phrase that just restates
 * that ("Declined offer") added nothing the status pill didn't already say, so
 * there is no plain "Declined"/"Successful" chip here. What a status pill
 * structurally cannot hold is *why* — so the outcome phrases are short, common
 * reasons instead. They still carry a {@see suggestedStatus()}; the panel
 * highlights that status pill for a few seconds after the phrase is inserted.
 * It is a nudge, never an automatic write — the client still confirms by
 * tapping the pill, the same deal as the tel:/mailto: → Called/Mailed nudge.
 * The reasons are a starting point, not an exhaustive list — anyone can keep
 * typing after the phrase, or ignore the chips and write their own.
 */
enum LeadNoteSnippet: string
{
    case CalledNoAnswer = 'called_no_answer';
    case CalledSpoke = 'called_spoke';
    case Mailed = 'mailed';
    case SentDetails = 'sent_details';
    case DeclinedPrice = 'declined_price';
    case DeclinedCompetitor = 'declined_competitor';
    case DeclinedTiming = 'declined_timing';
    case SuccessfulBooked = 'successful_booked';
    case SuccessfulSigned = 'successful_signed';

    /** The phrase inserted into the note box (also the chip's own label). */
    public function text(): string
    {
        return match ($this) {
            self::CalledNoAnswer => __('Called them, no answer'),
            self::CalledSpoke => __('Called them, spoke to them'),
            self::Mailed => __('Mailed them'),
            self::SentDetails => __('Sent them the details they asked for'),
            self::DeclinedPrice => __('Declined — price'),
            self::DeclinedCompetitor => __('Declined — chose a competitor'),
            self::DeclinedTiming => __('Declined — bad timing'),
            self::SuccessfulBooked => __('Successful — booked'),
            self::SuccessfulSigned => __('Successful — signed'),
        };
    }

    /** The status this phrase implies, if any — used for the pill nudge. */
    public function suggestedStatus(): ?LeadStatus
    {
        return match ($this) {
            self::DeclinedPrice, self::DeclinedCompetitor, self::DeclinedTiming => LeadStatus::Declined,
            self::SuccessfulBooked, self::SuccessfulSigned => LeadStatus::Successful,
            default => null,
        };
    }

    /** @return array<int, array{value: string, text: string, status: string|null}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $s) => [
                'value' => $s->value,
                'text' => $s->text(),
                'status' => $s->suggestedStatus()?->value,
            ],
            self::cases()
        );
    }
}
