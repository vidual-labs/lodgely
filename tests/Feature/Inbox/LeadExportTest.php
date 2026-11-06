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
            $reader = Reader::createFromString($body);
            $reader->setHeaderOffset(0);

            return collect(iterator_to_array($reader->getRecords(), false))->keyBy('full_name')->all();
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
        $lines = array_values(array_filter(preg_split("/\r\n|\n/", $body)));

        $this->assertCount(4, $lines, 'expected header + 3 rows');
        $header = str_getcsv($lines[0]);
        $this->assertSame(LeadExportController::COLUMNS, $header);

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
            $this->assertSame(LeadExportController::COLUMNS, array_keys($decoded));
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
        $body = $this->streamBody($response->baseResponse);
        $lines = array_values(array_filter(preg_split("/\r\n|\n/", $body)));
        $row = str_getcsv($lines[1]);

        $this->assertStringStartsWith("'=", $row[array_search('full_name', LeadExportController::COLUMNS)]);
        $this->assertStringStartsWith("'+", $row[array_search('message', LeadExportController::COLUMNS)]);
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
                $reader = Reader::createFromString($body);
                $reader->setHeaderOffset(0);
                $ids = array_map(fn (array $r) => (int) $r['id'], iterator_to_array($reader->getRecords(), false));
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
