<?php

namespace Tests\Unit;

use App\Domain\Ai\DTOs\LeadRanking;
use App\Domain\Leads\Enums\LeadPriority;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LeadRankingParseTest extends TestCase
{
    public function test_parses_plain_json(): void
    {
        $r = LeadRanking::parse('{"priority":"high","reason":"Asks for a quote for 25 guests.","tags":["quote request","budget stated"]}');

        $this->assertSame(LeadPriority::High, $r->priority);
        $this->assertSame('Asks for a quote for 25 guests.', $r->reason);
        $this->assertSame(['quote request', 'budget stated'], $r->tags);
    }

    public function test_tolerates_code_fences_and_prose_around_the_object(): void
    {
        $fenced = "```json\n{\"priority\": \"Low\", \"reason\": \"Looks like spam.\", \"tags\": [\"spam\"]}\n```";
        $prose  = "Sure! Here is the ranking:\n{\"priority\":\"medium\",\"reason\":\"Vague request.\"}\nLet me know if you need more.";

        $this->assertSame(LeadPriority::Low, LeadRanking::parse($fenced)->priority);
        $this->assertSame(['spam'], LeadRanking::parse($fenced)->tags);

        $r = LeadRanking::parse($prose);
        $this->assertSame(LeadPriority::Medium, $r->priority);
        $this->assertSame([], $r->tags);
    }

    public function test_unknown_priority_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LeadRanking::parse('{"priority":"urgent","reason":"x"}');
    }

    public function test_missing_reason_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LeadRanking::parse('{"priority":"high","reason":"   "}');
    }

    public function test_non_json_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LeadRanking::parse('I cannot rank this lead.');
    }

    public function test_tags_are_normalised_deduplicated_and_capped(): void
    {
        $r = LeadRanking::parse(json_encode([
            'priority' => 'high',
            'reason'   => "Multi\nline   reason",
            'tags'     => ['  Quote Request ', 'quote request', 'Wrong Région!', 'a-very-long-tag-that-goes-well-beyond-thirty-characters', 'one', 'two', 'three', ''],
        ]));

        $this->assertSame('Multi line reason', $r->reason);
        $this->assertCount(5, $r->tags);
        $this->assertSame(['quote request', 'wrong rgion', 'a-very-long-tag-that-goes-well', 'one', 'two'], $r->tags);
        foreach ($r->tags as $tag) {
            $this->assertMatchesRegularExpression('/^[a-z0-9][a-z0-9 -]*$/', $tag);
            $this->assertLessThanOrEqual(30, mb_strlen($tag));
        }
    }

    public function test_reason_is_truncated(): void
    {
        $r = LeadRanking::parse(json_encode(['priority' => 'low', 'reason' => str_repeat('a', 500)]));

        $this->assertSame(LeadRanking::MAX_REASON_LENGTH, mb_strlen($r->reason));
    }
}
