<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Timestamp presentation for lists someone actually reads back.
 *
 * "3 weeks ago" answers "is this recent?" and nothing else — the moment anyone
 * asks *when* a lead was called, a relative string is useless. So: relative
 * while the event is fresh enough for relative to mean something, an exact
 * timestamp once it is over a day old.
 *
 * The cutoff is deliberately a full day rather than "not today": an event from
 * 23:50 last night reads better as "10 hours ago" than as a date, and by the
 * time something is a day old the exact stamp is what you want.
 */
class Dates
{
    /** The app's house timestamp format — matches every other absolute date in the UI. */
    public const FORMAT = 'Y-m-d H:i';

    /**
     * Relative while it is under a day old, an exact timestamp after that.
     *
     * Locale handling comes for free: `diffForHumans()` follows the app locale
     * set by {@see \App\Http\Middleware\SetLocale}, and {@see FORMAT} is
     * locale-invariant by construction.
     */
    public static function relativeOrExact(?CarbonInterface $at): string
    {
        if ($at === null) {
            return '';
        }

        return $at->greaterThan(now()->subDay())
            ? $at->diffForHumans()
            : $at->format(self::FORMAT);
    }
}
