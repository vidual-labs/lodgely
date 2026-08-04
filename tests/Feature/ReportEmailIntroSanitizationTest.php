<?php

namespace Tests\Feature;

use App\Domain\Reporting\Services\ReportEmailComposer;
use App\Models\ClientReportEmail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The composed intro goes into the client's email body and the in-app preview
 * unescaped ({!! !!} in mail/client-report.blade.php), so whatever survives
 * composition is what executes in the reader's client.
 */
class ReportEmailIntroSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $this->client = User::create([
            'name' => 'Client', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);
    }

    private function introHtml(string $markdown): string
    {
        $operator = User::create([
            'name' => 'Op', 'email' => 'op'.uniqid().'@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);

        $email = ClientReportEmail::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'name' => 'Intro test',
            'include_kpi_strip' => false,
            'include_metrics_table' => false,
            'include_ai_summary' => false,
            'period_months' => 1,
            'subject_template' => 'Report',
            'intro_markdown' => $markdown,
            'is_active' => true,
            'created_by' => $operator->id,
        ]);

        return (string) app(ReportEmailComposer::class)->compose($email, $this->client)['intro_html'];
    }

    /** @return array<string, array{0:string}> */
    public static function dangerousIntros(): array
    {
        return [
            // The previous regex sanitizer only matched *quoted* href values.
            'raw anchor with unquoted javascript href' => ['<a href=javascript:alert(1)>click</a>'],
            // A link whose scheme passed was re-emitted verbatim, event handler
            // and all — strip_tags() filters tags, never attributes.
            'raw anchor with event handler' => ['<a href="https://ok.example" onclick="alert(1)">click</a>'],
            'markdown link with javascript scheme' => ['[click](javascript:alert(1))'],
            'markdown link with data uri' => ['[click](data:text/html;base64,PHNjcmlwdD4=)'],
            'raw image with onerror' => ['hello <img src=x onerror=alert(1)>'],
            'raw script tag' => ['<script>alert(1)</script>'],
            'raw iframe' => ['<iframe src="javascript:alert(1)"></iframe>'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dangerousIntros')]
    public function test_dangerous_intro_markup_never_reaches_the_rendered_html(string $markdown): void
    {
        $html = $this->introHtml($markdown);

        $this->assertStringNotContainsStringIgnoringCase('javascript:', $html);
        $this->assertStringNotContainsStringIgnoringCase('data:text/html', $html);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $html);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $html);
        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
        $this->assertStringNotContainsStringIgnoringCase('<iframe', $html);
        $this->assertStringNotContainsStringIgnoringCase('<img', $html);
    }

    public function test_legitimate_markdown_still_renders(): void
    {
        $html = $this->introHtml(
            "# Q3 wrap-up\n\nTraffic was **up**. See [the dashboard](https://example.com/dash)"
            ." or [email us](mailto:hi@example.com).\n\n- one\n- two"
        );

        $this->assertStringContainsString('<h1>Q3 wrap-up</h1>', $html);
        $this->assertStringContainsString('<strong>up</strong>', $html);
        $this->assertStringContainsString('<a href="https://example.com/dash">the dashboard</a>', $html);
        $this->assertStringContainsString('<a href="mailto:hi@example.com">email us</a>', $html);
        $this->assertStringContainsString('<li>one</li>', $html);
    }
}
