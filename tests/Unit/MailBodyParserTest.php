<?php

namespace Tests\Unit;

use App\Importers\Email\MailBodyParser;
use PHPUnit\Framework\TestCase;

class MailBodyParserTest extends TestCase
{
    private MailBodyParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MailBodyParser();
    }

    public function test_parses_labeled_plain_text_body(): void
    {
        $body = "Name: John Doe\nPhone: +44 7700 900123\nMessage: Please contact me soon.";

        $result = $this->parser->parse($body);

        $this->assertSame('John Doe', $result['name']);
        $this->assertSame('+44 7700 900123', $result['phone']);
        $this->assertSame('Please contact me soon.', $result['message']);
    }

    public function test_parses_alternative_field_labels(): void
    {
        $body = "Full Name: Jane Smith\nTelephone: 07700 900999\nEnquiry: Looking for availability.";

        $result = $this->parser->parse($body);

        $this->assertSame('Jane Smith', $result['name']);
        $this->assertSame('07700 900999', $result['phone']);
        $this->assertSame('Looking for availability.', $result['message']);
    }

    public function test_collects_multiline_message_after_bare_label(): void
    {
        $body = "Name: Sam Jones\nMessage:\nFirst line of the message.\nSecond line here.";

        $result = $this->parser->parse($body);

        $this->assertSame('Sam Jones', $result['name']);
        $this->assertSame("First line of the message.\nSecond line here.", $result['message']);
    }

    public function test_treats_unstructured_body_as_message(): void
    {
        $body = "Hi there, I'm interested in your services. Please get in touch.";

        $result = $this->parser->parse($body);

        $this->assertNull($result['name']);
        $this->assertNull($result['phone']);
        $this->assertSame($body, $result['message']);
    }

    public function test_parses_html_body(): void
    {
        $html = '<p>Name: Alex Taylor</p><p>Phone: +49 30 1234567</p><p>Message: Send me a quote.</p>';

        $result = $this->parser->parse($html, true);

        $this->assertSame('Alex Taylor', $result['name']);
        $this->assertSame('+49 30 1234567', $result['phone']);
        $this->assertSame('Send me a quote.', $result['message']);
    }

    public function test_strips_html_and_decodes_entities(): void
    {
        $html = '<p>Name: O&#39;Brien &amp; Co</p>';

        $result = $this->parser->parse($html, true);

        $this->assertSame("O'Brien & Co", $result['name']);
    }

    public function test_returns_null_for_empty_field_values(): void
    {
        $body = "Name:\nMessage: Something";

        $result = $this->parser->parse($body);

        $this->assertNull($result['name']);
        $this->assertSame('Something', $result['message']);
    }

    public function test_empty_body_yields_null_message(): void
    {
        $result = $this->parser->parse('   ');

        $this->assertNull($result['name']);
        $this->assertNull($result['phone']);
        $this->assertNull($result['message']);
    }

    public function test_does_not_overwrite_first_match_with_later_duplicate_label(): void
    {
        $body = "Name: First Person\nName: Second Person";

        $result = $this->parser->parse($body);

        $this->assertSame('First Person', $result['name']);
    }

    public function test_mobile_label_resolves_to_phone(): void
    {
        $body = "Mobile: 07911 123456";

        $result = $this->parser->parse($body);

        $this->assertSame('07911 123456', $result['phone']);
    }
}
