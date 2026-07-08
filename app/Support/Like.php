<?php

namespace App\Support;

/**
 * Escaping for user-supplied terms interpolated into SQL LIKE patterns.
 *
 * Without this, a search for "100%" matches any lead containing "100", "_"
 * matches every single-character position, and a stray backslash silently
 * eats the following wildcard. Escaped terms must be matched with an
 * explicit `ESCAPE '\'` clause so Postgres (default escape) and SQLite
 * (no default escape) behave identically.
 */
final class Like
{
    /** Escape \, % and _ so they match literally inside a LIKE pattern. */
    public static function escape(string $term): string
    {
        return addcslashes($term, '\\%_');
    }
}
