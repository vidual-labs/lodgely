<?php

namespace Tests\Feature;

use App\Domain\Api\Enums\ApiTokenScope;
use App\Domain\Leads\Services\LeadMetadataFeed;
use App\Models\ApiToken;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Contract test for `GET /api/v1/lead-metadata` (LG-15), the feed vigilo
 * reads. The shape follows vigilo's docs/LODGELY-CONTRACT.md (contract v1);
 * the PII test is the one the contract asks lodgely to keep.
 */
class LeadMetadataApiTest extends TestCase
{
    use RefreshDatabase;

    /** Keys that must never appear anywhere in a response. */
    private const FORBIDDEN_KEYS = [
        'full_name', 'email', 'email_normalized', 'phone', 'phone_normalized', 'message',
        'custom_answers', 'raw_payload', 'notes', 'ai_reason', 'ai_tags', 'gclid', 'gbraid',
        'wbraid', 'fbclid', 'ip', 'client_name', 'campaign_name', 'ad_name', 'adset_name', 'form_name',
    ];

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
        [, $this->token] = ApiToken::issue(Tenant::DEFAULT_ID, 'vigilo', [ApiTokenScope::LeadMetadataRead]);
    }

    /** @param array<string, mixed> $query */
    private function feed(array $query = [], ?string $token = null): TestResponse
    {
        return $this->withHeaders([
            'Authorization'     => 'Bearer '.($token ?? $this->token),
            'Accept'            => 'application/json',
            'X-Vigilo-Contract' => '1',
        ])->get('/api/v1/lead-metadata?'.http_build_query($query + ['client' => 'kanzlei-brenner-partner']));
    }

    /** @param array<string, mixed> $attributes */
    private function lead(array $attributes = []): Lead
    {
        return Lead::factory()->create($attributes + ['client_name' => 'Kanzlei Brenner & Partner']);
    }

    // ------------------------------------------------------------ auth

    public function test_a_missing_or_unknown_token_is_401(): void
    {
        $this->lead();

        $this->getJson('/api/v1/lead-metadata?client=kanzlei-brenner-partner')->assertStatus(401);
        $this->feed(token: 'lgy_not-a-real-token')->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_a_revoked_token_is_401(): void
    {
        $this->lead();
        ApiToken::query()->update(['revoked_at' => now()]);

        $this->feed()->assertStatus(401);
    }

    public function test_a_token_without_the_scope_is_403(): void
    {
        $this->lead();
        [, $plain] = ApiToken::issue(Tenant::DEFAULT_ID, 'nothing', []);

        $this->feed(token: $plain)->assertStatus(403);
    }

    public function test_the_token_is_stored_hashed_and_its_use_recorded(): void
    {
        $this->lead();

        $row = ApiToken::query()->sole();
        $this->assertNotSame($this->token, $row->token_hash);
        $this->assertSame(hash('sha256', $this->token), $row->token_hash);
        $this->assertNull($row->last_used_at);

        $this->feed()->assertOk();

        $this->assertNotNull($row->fresh()->last_used_at);
    }

    // ------------------------------------------------------------ shape

    public function test_it_returns_contract_v1_metadata(): void
    {
        $lead = $this->lead([
            'source'       => 'meta_leads',
            'status'       => 'successful',
            'campaign_id'  => '2385011',
            'adset_id'     => '23850119',
            'ad_id'        => '2385011002',
            'form_id'      => '9001',
            'qualified_at' => now(),
            'called_at'    => now(),
            'is_organic'   => false,
            'created_at'   => $this->utc('2026-10-19T08:15:00Z'),
        ]);
        LeadEvent::create([
            'lead_id' => $lead->id, 'type' => 'lead.status_changed',
            'payload' => ['from' => 'new', 'to' => 'successful'], 'created_at' => $this->utc('2026-10-20T07:00:00Z'),
        ]);

        $response = $this->feed()->assertOk();

        $response->assertJsonStructure([
            'contract_version', 'client', 'next_cursor', 'has_more',
            'data' => [['id', 'submitted_at', 'updated_at', 'status', 'status_changed_at', 'source_type',
                'attribution' => ['meta_campaign_id', 'meta_adset_id', 'meta_ad_id', 'meta_form_id', 'has_gclid', 'utm_source', 'utm_medium', 'utm_campaign'],
                'flags' => ['duplicate', 'qualified', 'contacted', 'organic'],
            ]],
        ]);
        $response->assertJson([
            'contract_version' => 1,
            'client'           => 'kanzlei-brenner-partner',
            'has_more'         => false,
            'data'             => [[
                'id'                => 'ld_'.$lead->id,
                'submitted_at'      => '2026-10-19T08:15:00Z',
                'status'            => 'successful',
                'status_changed_at' => '2026-10-20T07:00:00Z',
                'source_type'       => 'meta_lead_ads',
                'attribution'       => [
                    'meta_campaign_id' => '2385011',
                    'meta_adset_id'    => '23850119',
                    'meta_ad_id'       => '2385011002',
                    'meta_form_id'     => '9001',
                    'has_gclid'        => false,
                ],
                'flags' => ['duplicate' => false, 'qualified' => true, 'contacted' => true, 'organic' => false],
            ]],
        ]);

        $item = $response->json('data.0');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_\-:.]{1,128}$/', $item['id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $item['updated_at']);
        $this->assertIsString($response->json('next_cursor'));
    }

    public function test_the_payload_never_contains_personal_data(): void
    {
        $lead = $this->lead([
            'full_name'      => 'Erika Mustermann',
            'email'          => 'erika@example.com',
            'phone'          => '+49 30 1234567',
            'message'        => 'Bitte rufen Sie mich an',
            'custom_answers' => ['budget' => 'secret answer'],
            'raw_payload'    => ['gclid' => 'Cj0KCQ-click-id', 'ip' => '203.0.113.9'],
            'ai_reason'      => 'AI reasoning about Erika',
        ]);
        $this->lead(['full_name' => 'Gelöschte Person', 'email' => 'deleted@example.com'])->delete();

        $response = $this->feed()->assertOk();

        $this->assertForbiddenKeysAbsent($response->json());
        foreach (['Erika', 'erika@example.com', '1234567', 'Bitte rufen', 'secret answer', 'Cj0KCQ', '203.0.113.9', 'Gelöschte', 'deleted@example.com', 'Kanzlei Brenner'] as $value) {
            $this->assertStringNotContainsString($value, $response->getContent());
        }
        $this->assertCount(2, $response->json('data'));
        $this->assertSame('ld_'.$lead->id, $response->json('data.0.id'));
    }

    public function test_source_keys_map_to_the_contract_vocabulary(): void
    {
        $this->assertSame('meta_lead_ads', LeadMetadataFeed::sourceType('meta_leads'));
        $this->assertSame('meta_lead_ads', LeadMetadataFeed::sourceType('meta_ads'));
        $this->assertSame('email', LeadMetadataFeed::sourceType('email_imap'));
        $this->assertSame('email', LeadMetadataFeed::sourceType('email_mock'));
        $this->assertSame('openflow', LeadMetadataFeed::sourceType('openflow'));
        $this->assertSame('some_new_source', LeadMetadataFeed::sourceType('some_new_source'));
        $this->assertNull(LeadMetadataFeed::sourceType('Not A Key!'));
        $this->assertNull(LeadMetadataFeed::sourceType(null));
    }

    // ------------------------------------------------------------ client key

    public function test_unknown_client_is_404(): void
    {
        $this->lead();

        $this->feed(['client' => 'nobody'])->assertStatus(404)->assertJson(['message' => 'Unknown client.']);
    }

    public function test_the_client_key_is_the_slug_and_scopes_the_feed(): void
    {
        $mine = $this->lead();
        $variant = $this->lead(['client_name' => 'kanzlei brenner partner']);
        $this->lead(['client_name' => 'Someone Else']);
        $this->lead(['client_name' => null]);

        $ids = collect($this->feed()->assertOk()->json('data'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing(['ld_'.$mine->id, 'ld_'.$variant->id], $ids);
    }

    public function test_client_is_required_and_limit_is_bounded(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token, 'Accept' => 'application/json'])
            ->get('/api/v1/lead-metadata')->assertStatus(422);
        $this->feed(['limit' => 0])->assertStatus(422);
        $this->feed(['limit' => 501])->assertStatus(422);
    }

    // ------------------------------------------------------------ change feed

    public function test_it_pages_through_changes_with_an_opaque_cursor(): void
    {
        $leads = collect(range(1, 5))->map(fn ($i) => $this->lead(['updated_at' => now()->subMinutes(10 - $i)]));

        $first = $this->feed(['limit' => 2])->assertOk();
        $this->assertTrue($first->json('has_more'));
        $this->assertSame(['ld_'.$leads[0]->id, 'ld_'.$leads[1]->id], collect($first->json('data'))->pluck('id')->all());

        $second = $this->feed(['limit' => 2, 'cursor' => $first->json('next_cursor')])->assertOk();
        $this->assertTrue($second->json('has_more'));
        $this->assertSame(['ld_'.$leads[2]->id, 'ld_'.$leads[3]->id], collect($second->json('data'))->pluck('id')->all());

        $third = $this->feed(['limit' => 2, 'cursor' => $second->json('next_cursor')])->assertOk();
        $this->assertFalse($third->json('has_more'));
        $this->assertSame(['ld_'.$leads[4]->id], collect($third->json('data'))->pluck('id')->all());

        // Nothing changed: empty page, same cursor back.
        $idle = $this->feed(['cursor' => $third->json('next_cursor')])->assertOk();
        $this->assertSame([], $idle->json('data'));
        $this->assertSame($third->json('next_cursor'), $idle->json('next_cursor'));
        $this->assertFalse($idle->json('has_more'));
    }

    public function test_leads_sharing_an_updated_at_are_not_skipped_between_pages(): void
    {
        $at = now()->subHour()->startOfSecond();
        $leads = collect(range(1, 3))->map(fn () => $this->lead(['updated_at' => $at]));

        $first = $this->feed(['limit' => 2])->assertOk();
        $second = $this->feed(['limit' => 2, 'cursor' => $first->json('next_cursor')])->assertOk();

        $ids = collect($first->json('data'))->merge($second->json('data'))->pluck('id')->all();
        $this->assertSame($leads->map(fn ($l) => 'ld_'.$l->id)->all(), $ids);
    }

    public function test_a_status_change_on_an_old_lead_reappears_after_the_cursor(): void
    {
        $old = $this->lead(['updated_at' => now()->subDays(3)]);
        $cursor = $this->feed()->assertOk()->json('next_cursor');

        $this->travel(1)->minutes();
        $old->update(['status' => 'successful']);

        $next = $this->feed(['cursor' => $cursor])->assertOk();
        $this->assertSame('ld_'.$old->id, $next->json('data.0.id'));
        $this->assertSame('successful', $next->json('data.0.status'));
    }

    public function test_deleted_leads_go_out_as_tombstones(): void
    {
        $lead = $this->lead(['updated_at' => now()->subDays(2)]);
        $cursor = $this->feed()->assertOk()->json('next_cursor');

        $this->travel(1)->minutes();
        $lead->delete();

        $item = $this->feed(['cursor' => $cursor])->assertOk()->json('data.0');
        $this->assertSame(['id', 'deleted', 'updated_at'], array_keys($item));
        $this->assertSame('ld_'.$lead->id, $item['id']);
        $this->assertTrue($item['deleted']);
    }

    public function test_updated_since_applies_only_without_a_cursor(): void
    {
        $this->lead(['updated_at' => now()->subDays(90)]);
        $recent = $this->lead(['updated_at' => now()->subDays(5)]);

        $since = now()->subDays(60)->utc()->toIso8601String();
        $ids = collect($this->feed(['updated_since' => $since])->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame(['ld_'.$recent->id], $ids);
    }

    public function test_a_tampered_cursor_is_400(): void
    {
        $this->lead();

        $this->feed(['cursor' => 'not-a-cursor'])->assertStatus(400)->assertJson(['message' => 'Invalid cursor.']);
        $injected = LeadMetadataFeed::encodeCursor("2026-01-01' OR 1=1 --", 1);
        $this->feed(['cursor' => $injected])->assertStatus(400);
    }

    // ------------------------------------------------------------ limits & logging

    public function test_it_is_rate_limited_per_token_with_retry_after(): void
    {
        $this->lead();

        for ($i = 0; $i < 60; $i++) {
            $this->feed(['limit' => 1])->assertOk();
        }

        $this->feed(['limit' => 1])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_every_request_is_logged_without_lead_data(): void
    {
        $this->lead(['email' => 'logged@example.com']);
        Log::spy();

        $this->feed()->assertOk();

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) {
            return $message === 'lodgely.api.lead_metadata'
                && $context['client'] === 'kanzlei-brenner-partner'
                && $context['rows'] === 1
                && is_int($context['api_token_id'])
                && ! str_contains(json_encode($context), 'logged@example.com');
        })->once();
    }

    /** A UTC instant in the app timezone, the way Eloquent stores timestamps. */
    private function utc(string $iso): Carbon
    {
        return Carbon::parse($iso)->setTimezone(config('app.timezone'));
    }

    /** @param mixed $node */
    private function assertForbiddenKeysAbsent($node, string $path = ''): void
    {
        if (! is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if (is_string($key)) {
                $this->assertNotContains($key, self::FORBIDDEN_KEYS, "PII key '{$key}' leaked at {$path}");
            }
            $this->assertForbiddenKeysAbsent($value, $path.'.'.$key);
        }
    }
}
