<?php

namespace App\Domain\Leads\Enums;

/**
 * Who last wrote leads.priority. Null means "nobody explicitly" — the import
 * default or a value carried in from a source file. The inbox shows the AI
 * sparkle only while the source is Ai; any human change flips it to User and
 * the ranker never touches that lead again (unless an operator forces a
 * re-run from the lead panel).
 */
enum PrioritySource: string
{
    case Ai   = 'ai';
    case User = 'user';
}
