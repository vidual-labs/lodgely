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

    public function test_masked_lead_scrubs_openflow_payload_with_opaque_field_ids(): void
    {
        $p = new Pseudonymizer();

        $lead = new Lead();
        $lead->id = 7;
        $lead->full_name = 'Jane Doe';
        $lead->email = 'jane.doe@example.com';
        $lead->phone = '+49 30 1234567';
        // The shape OpenflowLeadSource stores: answers keyed by generated
        // field ids, plus the request metadata OpenFlow captured.
        $lead->raw_payload = [
            'id' => 'b7c1c2a4-0000-4000-8000-000000000000',
            'data' => [
                'field_1727000000001' => 'Jane Doe',
                'field_1727000000002' => 'jane.doe@example.com',
                'field_1727000000003' => '+49 30 1234567',
                'field_1727000000004' => 'Jane',
                'field_1727000000005' => 'other@example.org',
                'field_1727000000006' => '0151 23456789',
                'field_1727000000007' => '10.000 – 25.000 €',
                'field_1727000000008' => '2026-10-02',
                'field_1727000000009' => 'data:application/pdf;base64,JVBERi0x',
                'field_1727000000010' => ['street' => 'Hauptstr. 1', 'postalCode' => '10115', 'city' => 'Berlin'],
                '_consent' => true,
            ],
            'metadata' => [
                'ip' => '203.0.113.9',
                'userAgent' => 'Mozilla/5.0',
                'referer' => 'https://example.com/landing',
                'gclid' => 'Cj0KCQ',
                'fbc' => 'fb.1.1.abc',
                'submittedAt' => '2026-09-29T10:00:00.000Z',
            ],
        ];

        $out = $p->maskedLead($lead);
        $json = json_encode($out);

        foreach (['Jane', 'jane.doe@example.com', '1234567', 'other@example.org', '23456789', 'Hauptstr', '203.0.113.9', 'Mozilla', 'Cj0KCQ', 'fb.1.1.abc', 'base64'] as $needle) {
            $this->assertStringNotContainsString($needle, $json, "leaked: {$needle}");
        }
        // What the model needs to judge the lead survives.
        $this->assertSame('10.000 – 25.000 €', $out['raw_payload']['data']['field_1727000000007']);
        $this->assertSame('2026-10-02', $out['raw_payload']['data']['field_1727000000008']);
        $this->assertTrue($out['raw_payload']['data']['_consent']);
        $this->assertSame('2026-09-29T10:00:00.000Z', $out['raw_payload']['metadata']['submittedAt']);
    }

    public function test_custom_answers_are_included_with_identifying_answers_dropped(): void
    {
        $p = new Pseudonymizer();

        $lead = new Lead();
        $lead->full_name = 'Jane Doe';
        $lead->email = 'jane.doe@example.com';
        $lead->phone = '+49 30 1234567';
        $lead->custom_answers = [
            ['question' => 'Guests', 'answer' => '25'],
            ['question' => 'Your email', 'answer' => 'jane.doe@example.com'],
            ['question' => 'Budget per person', 'answer' => '45 EUR'],
        ];

        $out = $p->maskedLead($lead);
        $json = json_encode($out['custom_answers']);

        $this->assertStringContainsString('25', $json);
        $this->assertStringContainsString('45 EUR', $json);
        $this->assertStringNotContainsString('jane.doe@', $json);
    }
}
