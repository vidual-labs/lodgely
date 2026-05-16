<?php

namespace Tests\Unit;

use App\Domain\Ai\Support\Pseudonymizer;
use App\Models\Lead;
use PHPUnit\Framework\TestCase;

class PseudonymizerTest extends TestCase
{
    public function test_email_keeps_first_letter_and_domain(): void
    {
        $p = new Pseudonymizer();

        $this->assertSame('j***@example.com', $p->maskEmail('jane.doe@example.com'));
        $this->assertSame('a***@example.org', $p->maskEmail('alex@example.org'));
        $this->assertNull($p->maskEmail(null));
        $this->assertSame('***', $p->maskEmail('no-at-sign'));
    }

    public function test_phone_keeps_country_code_and_last_two_digits(): void
    {
        $p = new Pseudonymizer();

        $this->assertStringEndsWith(' 67', $p->maskPhone('+49 30 1234567'));
        $this->assertStringContainsString('***', $p->maskPhone('+49 30 1234567'));
        $this->assertNull($p->maskPhone(null));
        $this->assertSame('***', $p->maskPhone('abc'));
    }

    public function test_strip_pii_keys_removes_obvious_personal_keys(): void
    {
        $p = new Pseudonymizer();

        $out = $p->stripPiiKeys([
            'full_name' => 'Jane',
            'firstName' => 'Jane',
            'email'     => 'j@example.com',
            'phone'     => '+49 30',
            'city'      => 'Berlin',
            'message'   => 'I want a quote.',
            'budget'    => '5000',
            'nested'    => ['name' => 'X', 'product' => 'Y'],
        ]);

        $this->assertArrayNotHasKey('full_name', $out);
        $this->assertArrayNotHasKey('firstName', $out);
        $this->assertArrayNotHasKey('email', $out);
        $this->assertArrayNotHasKey('phone', $out);
        $this->assertArrayNotHasKey('city', $out);
        $this->assertSame('I want a quote.', $out['message']);
        $this->assertSame('5000', $out['budget']);
        $this->assertSame(['product' => 'Y'], $out['nested']);
    }

    public function test_masked_lead_replaces_full_name_with_ref(): void
    {
        $p = new Pseudonymizer();

        $lead = new Lead();
        $lead->id = 42;
        $lead->full_name = 'Jane Doe';
        $lead->email = 'jane@example.com';
        $lead->phone = '+49 30 1234567';
        $lead->message = 'Need a quote.';
        $lead->client_name = 'Acme';
        $lead->campaign_name = 'Spring';
        $lead->raw_payload = ['name' => 'Jane', 'product_interest' => 'X'];

        $out = $p->maskedLead($lead);

        $this->assertSame('Lead #42', $out['lead_ref']);
        $this->assertStringNotContainsString('Jane', json_encode($out));
        $this->assertSame('Acme', $out['client_name']);
        $this->assertSame('Need a quote.', $out['message']);
        $this->assertSame(['product_interest' => 'X'], $out['raw_payload']);
    }
}
