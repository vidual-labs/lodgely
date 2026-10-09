<?php

namespace App\Domain\Ai\DTOs;

use App\Domain\Leads\Enums\LeadPriority;
use InvalidArgumentException;

/**
 * The parsed answer of a lead-ranking call. The model is told to reply with
 * one bare JSON object, but models wrap it in code fences or prose often
 * enough that parse() tolerates both; everything else is rejected so a
 * malformed answer can never move a lead's priority.
 */
final class LeadRanking
{
    public const MAX_REASON_LENGTH = 300;
    public const MAX_TAGS = 5;
    public const MAX_TAG_LENGTH = 30;

    /** @param list<string> $tags */
    public function __construct(
        public readonly LeadPriority $priority,
        public readonly string $reason,
        public readonly array $tags = [],
    ) {
    }

    public static function parse(string $text): self
    {
        $json = self::extractJson($text);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new InvalidArgumentException('Response is not a JSON object.');
        }

        $priorityRaw = $data['priority'] ?? null;
        $priority = is_string($priorityRaw) ? LeadPriority::tryFrom(mb_strtolower(trim($priorityRaw))) : null;
        if (! $priority) {
            throw new InvalidArgumentException('Unknown or missing "priority" (expected low, medium or high).');
        }

        $reasonRaw = $data['reason'] ?? null;
        $reason = is_string($reasonRaw) ? trim((string) preg_replace('/\s+/u', ' ', $reasonRaw)) : '';
        if ($reason === '') {
            throw new InvalidArgumentException('Missing "reason".');
        }
        $reason = mb_substr($reason, 0, self::MAX_REASON_LENGTH);

        return new self($priority, $reason, self::normalizeTags($data['tags'] ?? []));
    }

    /** @return array{priority: string, reason: string, tags: list<string>} */
    public function toArray(): array
    {
        return ['priority' => $this->priority->value, 'reason' => $this->reason, 'tags' => $this->tags];
    }

    /**
     * Strip markdown fences and any prose around the object, then keep what
     * sits between the first "{" and the last "}".
     */
    private static function extractJson(string $text): string
    {
        $text = trim($text);
        $text = (string) preg_replace('/^```[a-zA-Z]*\s*/', '', $text);
        $text = (string) preg_replace('/\s*```$/', '', $text);

        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            throw new InvalidArgumentException('No JSON object found in the response.');
        }

        return substr($text, $start, $end - $start + 1);
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private static function normalizeTags(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $tags = [];
        foreach ($raw as $tag) {
            if (! is_string($tag) && ! is_numeric($tag)) {
                continue;
            }
            $tag = mb_strtolower(trim((string) $tag));
            $tag = (string) preg_replace('/[^a-z0-9 -]/', '', $tag);
            $tag = trim((string) preg_replace('/\s+/', ' ', $tag), ' -');
            $tag = mb_substr($tag, 0, self::MAX_TAG_LENGTH);
            if ($tag === '' || in_array($tag, $tags, true)) {
                continue;
            }
            $tags[] = $tag;
            if (count($tags) >= self::MAX_TAGS) {
                break;
            }
        }

        return $tags;
    }
}
