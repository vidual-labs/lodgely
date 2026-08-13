<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserLeadScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Walks the operator/client boundary as a whole rather than one page at a time.
 *
 * Individual pages already assert their own guards, but nothing asserted the
 * *set*. Clients here are external small-business owners with real accounts in
 * the same install, and the operator-only surface includes every stored
 * integration credential (Meta, Google Ads, SMTP, AI) plus destructive and
 * data-creating actions. A single guard forgotten on a new route is all it
 * takes, and that is exactly the kind of omission a per-page test suite cannot
 * see. Adding a route to the operator-only list below should be part of adding
 * the route.
 *
 * See CLAUDE.md for which actions are deliberately open to clients — status /
 * priority / outreach toggles, notes, bulk status edits and CSV export — and
 * which stay operator-only.
 */
class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function tenant(): void
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function client(string $scope = 'Acme'): User
    {
        $this->tenant();

        $user = User::create([
            'name' => 'Client',
            'email' => 'client@example.com',
            'password' => Hash::make('password-12345'),
            'role' => 'client',
            'is_active' => true,
        ]);

        UserLeadScope::create(['user_id' => $user->id, 'client_name' => $scope]);

        return $user;
    }

    private function operator(): User
    {
        $this->tenant();

        return User::create([
            'name' => 'Operator',
            'email' => 'operator@example.com',
            'password' => Hash::make('password-12345'),
            'role' => 'operator',
            'is_active' => true,
        ]);
    }

    // ------------------------------------------------------- operator-only GETs

    /** @return array<string, array{0: string}> */
    public static function operatorOnlyPages(): array
    {
        return [
            'users' => ['/users'],
            'webhooks' => ['/webhooks'],
            'ad platforms' => ['/settings/ad-platforms'],
            'mail settings' => ['/settings/mail'],
            'google sheets settings' => ['/settings/google-sheets'],
            'backups' => ['/settings/backups'],
            'demo data' => ['/settings/demo-data'],
            'csv import' => ['/imports/csv'],
            'email mock import' => ['/imports/email'],
            'imap import' => ['/imports/email-imap'],
            'google sheets import' => ['/imports/google-sheets'],
            'meta leads import' => ['/imports/meta-leads'],
            'openflow import' => ['/imports/openflow'],
        ];
    }

    #[DataProvider('operatorOnlyPages')]
    public function test_a_client_cannot_open_an_operator_only_page(string $path): void
    {
        $this->actingAs($this->client())->get($path)->assertForbidden();
    }

    #[DataProvider('operatorOnlyPages')]
    public function test_an_operator_can_open_it(string $path): void
    {
        $this->actingAs($this->operator())->get($path)->assertSuccessful();
    }

    // ----------------------------------------------------- operator-only POSTs

    /** @return array<string, array{0: string, 1: string}> */
    public static function operatorOnlyActions(): array
    {
        return [
            'create backup' => ['post', '/settings/backups/create'],
            'delete backup' => ['post', '/settings/backups/delete'],
            'restore backup' => ['post', '/settings/backups/restore'],
            'fetch ad metrics' => ['post', '/reporting/ad-metrics/fetch'],
            'purge ad metrics' => ['post', '/reporting/ad-metrics/purge'],
            'create ad connector' => ['post', '/settings/ad-platforms/connectors'],
            'google ads oauth connect' => ['get', '/settings/ad-platforms/google/connect'],
            'google ads oauth callback' => ['get', '/settings/ad-platforms/google/callback'],
            'google sheets oauth connect' => ['get', '/settings/google-sheets/connect'],
            'google sheets oauth callback' => ['get', '/settings/google-sheets/callback'],
            'delete all sheet imports' => ['post', '/imports/google-sheets/imports'],
            'delete all meta imports' => ['post', '/imports/meta-leads/imports'],
            'delete all openflow imports' => ['post', '/imports/openflow/imports'],
        ];
    }

    #[DataProvider('operatorOnlyActions')]
    public function test_a_client_cannot_invoke_an_operator_only_action(string $verb, string $path): void
    {
        $this->actingAs($this->client())->{$verb}($path)->assertForbidden();
    }

    public function test_a_client_cannot_download_a_backup(): void
    {
        // Guarded before the file is looked up, so a 403 (not a 404) is the
        // correct answer even for a name that does not exist — the client must
        // not be able to probe which archives are on disk.
        $this->actingAs($this->client())
            ->get('/settings/backups/lodgely-backup-20260101-000000.zip/download')
            ->assertForbidden();
    }

    // ------------------------------------------------------------------ export

    public function test_ndjson_export_is_operator_only(): void
    {
        $this->actingAs($this->client())->get('/inbox/export?format=ndjson')->assertForbidden();
    }

    public function test_csv_export_is_open_to_clients_but_scoped_to_their_own_leads(): void
    {
        $client = $this->client('Acme');

        Lead::factory()->create(['client_name' => 'Acme', 'email' => 'theirs@example.com']);
        Lead::factory()->create(['client_name' => 'Other Co', 'email' => 'not-theirs@example.com']);

        $response = $this->actingAs($client)->get('/inbox/export?format=csv');
        $response->assertSuccessful();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('theirs@example.com', $csv);
        $this->assertStringNotContainsString('not-theirs@example.com', $csv);
    }

    public function test_an_operator_sees_every_client_in_the_csv_export(): void
    {
        $operator = $this->operator();

        Lead::factory()->create(['client_name' => 'Acme', 'email' => 'one@example.com']);
        Lead::factory()->create(['client_name' => 'Other Co', 'email' => 'two@example.com']);

        $csv = $this->actingAs($operator)->get('/inbox/export?format=csv')->streamedContent();

        $this->assertStringContainsString('one@example.com', $csv);
        $this->assertStringContainsString('two@example.com', $csv);
    }

    // -------------------------------------------------------------- guests

    public function test_guests_are_redirected_to_login_rather_than_served_data(): void
    {
        $this->get('/inbox')->assertRedirect(route('login'));
        $this->get('/inbox/export?format=csv')->assertRedirect(route('login'));
        $this->get('/settings/ad-platforms')->assertRedirect(route('login'));
    }
}
