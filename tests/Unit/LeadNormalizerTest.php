<?php

namespace Tests\Unit;

use App\Domain\Leads\Services\LeadNormalizer;
use PHPUnit\Framework\TestCase;

class LeadNormalizerTest extends TestCase
{
    public function test_email_is_lowercased_and_plus_alias_stripped(): void
    {
        $normalizer = new LeadNormalizer();

        $this->assertSame('jane.doe@example.com', $normalizer->normalizeEmail('Jane.Doe+sales@Example.com'));
        $this->assertNull($normalizer->normalizeEmail(null));
        $this->assertNull($normalizer->normalizeEmail('   '));
    }

    public function test_phone_keeps_digits_only_and_drops_short_values(): void
    {
        $normalizer = new LeadNormalizer();

        $this->assertSame('49301234567', $normalizer->normalizePhone('+49 30 1234567'));
        $this->assertNull($normalizer->normalizePhone('1234'));
        $this->assertNull($normalizer->normalizePhone(null));
    }

    public function test_text_is_collapsed_and_trimmed(): void
    {
        $normalizer = new LeadNormalizer();

        $this->assertSame('Acme Wellness', $normalizer->normalizeText("  Acme   Wellness "));
        $this->assertNull($normalizer->normalizeText(''));
    }
}
