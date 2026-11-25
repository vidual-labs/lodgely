<?php

namespace Tests\Feature\Inbox;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Http\Controllers\LeadExportController;
use App\Livewire\Inbox\InboxPage;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserLeadScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use Livewire\Livewire;
use Tests\TestCase;

class LeadExportTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
    }

    private function clientFor(string $clientName): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $client = User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);

        UserLeadScope::create([
            'user_id' => $client->id,
            'tenant_id' => Tenant::DEFAULT_ID,
            'client_name' => $clientName,
        ]);

        return $client;
    }

    private function streamBody($response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /** @return list<array<string, string>> */
    private function csvRecords(string $body): array
    {
        $reader = Reader::createFromString($body);
        $reader->setDelimiter(LeadExportController::CSV_DELIMITER);
        $reader->setHeaderOffset(0);

        return iterator_to_array($reader->getRecords(), false);
    }

    /** @return list<string> */
    private function csvHeader(string $body): array
    {
        $reader = Reader::createFromString($body);
        $reader->setDelimiter(LeadExportController::CSV_DELIMITER);
        $reader->setHeaderOffset(0);

        return $reader->getHeader();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/inbox/export?format=csv')->assertRedirect('/login');
    }

    public function test_client_can_export_csv_scoped_to_their_own_leads(): void
    {
        $client = $this->clientFor('Acme');
        Lead::factory()->create(['client_name' => 'Acme', 'full_name' => 'Hers']);
        Lead::factory()->create(['client_name' => 'Other', 'full_name' => 'Not hers']);

        $response = $this->actingAs($client)->get('/inbox/export?format=csv');
        $response->assertOk();

        $body = $this->streamBody($response->baseResponse);
        $this->assertStringContainsString('Hers', $body);
        $this->assertStringNotContainsString('Not hers', $body);
    }

    /**
     * Duplicate detection matches across the whole tenant, so a client's lead
     * can be the duplicate of another client's lead. The lead panel hides that
     * original from the client (InboxPage scopes duplicateOf); the CSV export
     * still printed its id. A client's export only names originals the client
     * can see; operators keep the id.
     */
    public function test_a_clients_export_does_not_point_at_another_clients_lead(): void
    {
        $client = $this->clientFor('Acme');
        $foreign = Lead::factory()->create(['client_name' => 'Other']);
        $own = Lead::factory()->create(['client_name' => 'Acme']);
        Lead::factory()->create(['client_name' => 'Acme', 'full_name' => 'Dup of foreign', 'duplicate_flag' => true, 'duplicate_of_id' => $foreign->id]);
        Lead::factory()->create(['client_name' => 'Acme', 'full_name' => 'Dup of own', 'duplicate_flag' => true, 'duplicate_of_id' => $own->id]);

        $rows = function (User $user): array {
            $this->flushSession(); // a fresh login per user (the session pins the password hash)
            $body = $this->streamBody($this->actingAs($user)->get('/inbox/export?format=csv')->baseResponse);

            return collect($this->csvRecords($body))->keyBy('full_name')->all();
        };

        $forClient = $rows($client);
        $this->assertSame('', $forClient['Dup of foreign']['duplicate_of_id']);
        $this->assertSame('1', $forClient['Dup of foreign']['duplicate_flag']);
        $this->assertSame((string) $own->id, $forClient['Dup of own']['duplicate_of_id']);

        $forOperator = $rows($this->operator());
        $this->assertSame((string) $foreign->id, $forOperator['Dup of foreign']['duplicate_of_id']);
    }

    public function test_client_cannot_export_ndjson(): void
    {
        $client = $this->clientFor('Acme');

        $this->actingAs($client)
            ->get('/inbox/export?format=ndjson')
            ->assertForbidden();
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->actingAs($this->operator())
            ->get('/inbox/export?format=xml')
            ->assertStatus(422);
    }

    public function test_operator_csv_export_contains_all_leads_with_expected_columns(): void
    {
        $op = $this->operator();
        Lead::factory()->count(3)->create();

        $response = $this->actingAs($op)->get('/inbox/export?format=csv');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $this->streamBody($response->baseResponse);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'UTF-8 BOM so Excel renders umlauts');
        $this->assertCount(3, $this->csvRecords($body));
        $this->assertSame(LeadExportController::COLUMNS, $this->csvHeader($body), 'no custom questions, no extra columns');

        $this->assertStringNotContainsString('raw_payload', $body);
        $this->assertStringNotContainsString('email_normalized', $body);
        $this->assertStringNotContainsString('phone_normalized', $body);
    }

    public function test_ndjson_export_emits_one_object_per_line(): void
    {
        $op = $this->operator();
        Lead::factory()->count(2)->create();

        $response = $this->actingAs($op)->get('/inbox/export?format=ndjson');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-ndjson');

        $body = $this->streamBody($response->baseResponse);
        $lines = array_values(array_filter(explode("\n", $body)));
        $this->assertCount(2, $lines);

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            $this->assertIsArray($decoded);
            $this->assertSame([...LeadExportController::COLUMNS, 'custom_answers'], array_keys($decoded));
        }
    }

    public function test_status_filter_is_honoured(): void
    {
        $op = $this->operator();
        Lead::factory()->create(['status' => LeadStatus::New->value, 'full_name' => 'Nora New']);
        Lead::factory()->create(['status' => LeadStatus::Reviewed->value, 'full_name' => 'Roger Reviewed']);
        Lead::factory()->create(['status' => LeadStatus::Forwarded->value, 'full_name' => 'Fiona Forwarded']);

        $response = $this->actingAs($op)->get('/inbox/export?format=csv&status=new');
        $body = $this->streamBody($response->baseResponse);

        $this->assertStringContainsString('Nora New', $body);
        $this->assertStringNotContainsString('Roger Reviewed', $body);
        $this->assertStringNotContainsString('Fiona Forwarded', $body);
    }

    public function test_search_filter_is_honoured(): void
    {
        $op = $this->operator();
        Lead::factory()->create(['client_name' => 'Acme Wellness', 'full_name' => 'Alice A']);
        Lead::factory()->create(['client_name' => 'Northwind Studio', 'full_name' => 'Bob B']);

        $response = $this->actingAs($op)->get('/inbox/export?format=csv&q=acme');
        $body = $this->streamBody($response->baseResponse);

        $this->assertStringContainsString('Alice A', $body);
        $this->assertStringNotContainsString('Bob B', $body);
    }

    public function test_outreach_filter_is_honoured(): void
    {
        $op = $this->operator();
        Lead::factory()->create(['full_name' => 'Not contacted']);
        Lead::factory()->create(['full_name' => 'Called', 'called_at' => now()]);

        $response = $this->actingAs($op)->get('/inbox/export?format=csv&outreach=called');
        $body = $this->streamBody($response->baseResponse);

        $this->assertStringContainsString('Called', $body);
        $this->assertStringNotContainsString('Not contacted', $body);
    }

    public function test_priority_filter_is_honoured(): void
    {
        $op = $this->operator();
        Lead::factory()->create(['priority' => LeadPriority::High->value, 'full_name' => 'High Pri']);
        Lead::factory()->create(['priority' => LeadPriority::Low->value, 'full_name' => 'Low Pri']);

        $response = $this->actingAs($op)->get('/inbox/export?format=csv&priority=high');
        $body = $this->streamBody($response->baseResponse);

        $this->assertStringContainsString('High Pri', $body);
        $this->assertStringNotContainsString('Low Pri', $body);
    }

    public function test_csv_export_neutralizes_formula_injection(): void
    {
        $op = $this->operator();
        Lead::factory()->create(['full_name' => '=cmd|\' /C calc\'!A1', 'message' => '+2+3']);

        $response = $this->actingAs($op)->get('/inbox/export?format=csv');
        $row = $this->csvRecords($this->streamBody($response->baseResponse))[0];

        $this->assertStringStartsWith("'=", $row['full_name']);
        $this->assertStringStartsWith("'+", $row['message']);
    }

    public function test_csv_export_has_one_column_per_custom_question(): void
    {
        $op = $this->operator();
        $start = now()->subDay();
        Lead::factory()->create([
            'full_name' => 'Dr. Schulze',
            'created_at' => $start,
            'custom_answers' => [
                ['question' => 'company_name', 'answer' => 'Dr. Schulze Icking'],
                ['question' => 'zip_code', 'answer' => 'z:64625'],
                ['question' => ' city ', 'answer' => 'Bensheim'],
            ],
        ]);
        Lead::factory()->create([
            'full_name' => 'Other form',
            'created_at' => $start->copy()->addHour(),
            'custom_answers' => [
                ['question' => 'budget', 'answer' => ['5k', '10k']],
                ['question' => 'email', 'answer' => 'second@example.com'],
                ['question' => 'city', 'answer' => 'Köln'],
            ],
        ]);
        Lead::factory()->create(['full_name' => 'No answers', 'custom_answers' => null]);

        $body = $this->streamBody($this->actingAs($op)->get('/inbox/export?format=csv')->baseResponse);

        $this->assertSame(
            [...LeadExportController::COLUMNS, 'company_name', 'zip_code', 'city', 'budget', 'email (custom)'],
            $this->csvHeader($body),
            'questions in first-seen order, trimmed, deduplicated; a clash with a built-in column is suffixed',
        );

        $rows = collect($this->csvRecords($body))->keyBy('full_name');
        $this->assertSame('Dr. Schulze Icking', $rows['Dr. Schulze']['company_name']);
        $this->assertSame('Bensheim', $rows['Dr. Schulze']['city']);
        $this->assertSame('', $rows['Dr. Schulze']['budget']);
        $this->assertSame('Köln', $rows['Other form']['city']);
        $this->assertSame('5k, 10k', $rows['Other form']['budget']);
        $this->assertSame('second@example.com', $rows['Other form']['email (custom)']);
        $this->assertSame('', $rows['No answers']['company_name']);
    }

    public function test_a_clients_custom_columns_only_come_from_their_own_leads(): void
    {
        $client = $this->clientFor('Acme');
        Lead::factory()->create(['client_name' => 'Acme', 'custom_answers' => [['question' => 'acme_q', 'answer' => 'a']]]);
        Lead::factory()->create(['client_name' => 'Other', 'custom_answers' => [['question' => 'secret_q', 'answer' => 'b']]]);

        $header = $this->csvHeader($this->streamBody($this->actingAs($client)->get('/inbox/export?format=csv')->baseResponse));

        $this->assertContains('acme_q', $header);
        $this->assertNotContains('secret_q', $header);
    }

    public function test_custom_question_headers_are_neutralized_against_formula_injection(): void
    {
        $op = $this->operator();
        Lead::factory()->create(['custom_answers' => [['question' => '=HYPERLINK("x")', 'answer' => '=1+1']]]);

        $body = $this->streamBody($this->actingAs($op)->get('/inbox/export?format=csv')->baseResponse);

        $this->assertContains("'=HYPERLINK(\"x\")", $this->csvHeader($body));
        $this->assertSame("'=1+1", $this->csvRecords($body)[0]["'=HYPERLINK(\"x\")"]);
    }

    public function test_csv_export_includes_notes_outreach_and_ai_fields(): void
    {
        $op = $this->operator();
        $lead = Lead::factory()->create([
            'full_name' => 'Full row',
            'called_at' => '2026-11-06 13:59:00',
            'ai_reason' => 'Fits the ideal customer',
            'ai_tags' => ['dentist', 'hot'],
            'meta_lead_id' => 'm-123',
        ]);
        $lead->notes()->create(['user_id' => $op->id, 'body' => 'Called, wants an offer']);

        $row = $this->csvRecords($this->streamBody($this->actingAs($op)->get('/inbox/export?format=csv')->baseResponse))[0];

        $this->assertStringStartsWith('2026-11-06T13:59:00', $row['called_at']);
        $this->assertSame('', $row['mailed_at']);
        $this->assertStringContainsString('Op: Called, wants an offer', $row['notes']);
        $this->assertSame('Fits the ideal customer', $row['ai_reason']);
        $this->assertSame('dentist, hot', $row['ai_tags']);
        $this->assertSame('m-123', $row['meta_lead_id']);
    }

    public function test_ndjson_export_carries_custom_answers_and_notes_as_structured_data(): void
    {
        $op = $this->operator();
        $lead = Lead::factory()->create(['custom_answers' => [['question' => 'city', 'answer' => 'Bensheim']]]);
        $lead->notes()->create(['user_id' => $op->id, 'body' => 'hi']);

        $body = $this->streamBody($this->actingAs($op)->get('/inbox/export?format=ndjson')->baseResponse);
        $decoded = json_decode(trim($body), true);

        $this->assertSame(['city' => 'Bensheim'], $decoded['custom_answers']);
        $this->assertSame('hi', $decoded['notes'][0]['body']);
        $this->assertSame('Op', $decoded['notes'][0]['author']);
    }

    /**
     * The export streams in chunks of 500. Keyset chunking (lazyById) keeps
     * the inbox's created_at DESC order and only swaps the id tiebreaker for
     * "id > last id", so from the second chunk on it re-read the newest rows
     * and never reached the oldest ones: 1,100 leads exported as 999 rows,
     * 499 of them duplicates. Every lead must appear exactly once, in the
     * inbox's sort order.
     */
    public function test_export_larger_than_one_chunk_contains_every_lead_exactly_once_in_sort_order(): void
    {
        $op = $this->operator();
        $start = now()->subDays(30);
        $rows = [];
        for ($i = 0; $i < 1100; $i++) {
            $rows[] = Lead::factory()->make([
                'tenant_id' => Tenant::DEFAULT_ID,
                'created_at' => $start->copy()->addMinutes($i),
                'updated_at' => $start->copy()->addMinutes($i),
            ])->getAttributes();
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            Lead::query()->insert(array_map(fn (array $r) => array_map(
                fn ($v) => is_array($v) ? json_encode($v) : $v,
                $r,
            ), $chunk));
        }
        $this->assertSame(1100, Lead::count());

        foreach (['csv', 'ndjson'] as $format) {
            $response = $this->actingAs($op)->get("/inbox/export?format={$format}");
            $body = $this->streamBody($response->baseResponse);

            if ($format === 'csv') {
                $ids = array_map(fn (array $r) => (int) $r['id'], $this->csvRecords($body));
            } else {
                $ids = array_map(
                    fn (string $line) => (int) json_decode($line, true)['id'],
                    array_values(array_filter(explode("\n", $body))),
                );
            }

            $this->assertCount(1100, $ids, "{$format}: every lead exported");
            $this->assertCount(1100, array_unique($ids), "{$format}: no lead exported twice");
            $this->assertSame(
                Lead::query()->orderByDesc('created_at')->orderByDesc('id')->pluck('id')->all(),
                $ids,
                "{$format}: rows follow the inbox sort (newest first)",
            );
        }
    }

    /**
     * The Export CSV / JSON links were built from request()->query(). That is
     * the page URL only on the first render: every filter change re-renders
     * through Livewire's /livewire/update request, whose query is empty, so
     * after picking a client or status in the toolbar the links silently
     * exported *every* lead instead of the filtered set.
     */
    public function test_export_links_follow_filters_changed_in_the_toolbar(): void
    {
        $op = $this->operator();
        Lead::factory()->create(['client_name' => 'Acme']);

        $html = Livewire::actingAs($op)
            ->test(InboxPage::class)
            ->set('client', 'Acme')
            ->set('status', LeadStatus::New->value)
            ->set('search', 'jo')
            ->set('sort', 'name_asc')
            ->html();

        preg_match_all('#href="([^"]*/inbox/export\?[^"]*)"#', $html, $m);
        $this->assertCount(2, $m[1], 'CSV and JSON export links rendered');

        foreach ($m[1] as $href) {
            parse_str((string) parse_url(html_entity_decode($href), PHP_URL_QUERY), $query);
            $this->assertSame('Acme', $query['client'] ?? null, $href);
            $this->assertSame('new', $query['status'] ?? null, $href);
            $this->assertSame('jo', $query['q'] ?? null, $href);
            $this->assertSame('name_asc', $query['sort'] ?? null, $href);
            $this->assertContains($query['format'] ?? null, ['csv', 'ndjson'], $href);
            $this->assertArrayNotHasKey('priority', $query, 'empty filters stay out of the URL');
        }
    }

    public function test_export_is_logged(): void
    {
        $op = $this->operator();
        Lead::factory()->count(2)->create();

        Log::spy();

        $response = $this->actingAs($op)->get('/inbox/export?format=csv&status=new');
        $this->streamBody($response->baseResponse);

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($op) {
                return $message === 'lead.exported'
                    && $context['user_id'] === $op->id
                    && $context['format'] === 'csv'
                    && is_int($context['count'])
                    && ($context['filters']['status'] ?? null) === 'new';
            });
    }
}
