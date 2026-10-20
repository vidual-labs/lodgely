<?php

namespace Tests\Feature;

use App\Domain\Api\Enums\ApiTokenScope;
use App\Models\ApiToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiTokensPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function user(string $role): User
    {
        return User::create([
            'name'      => ucfirst($role),
            'email'     => $role.'@example.com',
            'password'  => Hash::make('password'),
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    public function test_an_operator_creates_a_token_and_sees_it_exactly_once(): void
    {
        $operator = $this->user('operator');

        $response = $this->actingAs($operator)->post(route('settings.api-tokens.store'), [
            'name'  => 'vigilo',
            'scope' => ApiTokenScope::LeadMetadataRead->value,
        ]);
        $response->assertRedirect(route('settings.api-tokens'));

        $plaintext = session('api-tokens.created');
        $this->assertIsString($plaintext);
        $this->assertStringStartsWith(ApiToken::PREFIX, $plaintext);

        $token = ApiToken::query()->sole();
        $this->assertSame('vigilo', $token->name);
        $this->assertSame([ApiTokenScope::LeadMetadataRead->value], $token->scopes);
        $this->assertSame($operator->id, $token->user_id);
        $this->assertSame(hash('sha256', $plaintext), $token->token_hash);
        $this->assertStringNotContainsString($plaintext, json_encode($token->getAttributes()));

        $this->get(route('settings.api-tokens'))->assertOk()->assertSee($plaintext)->assertSee('vigilo');
        $this->get(route('settings.api-tokens'))->assertOk()->assertDontSee($plaintext)->assertSee($token->token_prefix);
    }

    public function test_an_operator_revokes_a_token(): void
    {
        [$token, $plaintext] = ApiToken::issue(Tenant::DEFAULT_ID, 'vigilo', [ApiTokenScope::LeadMetadataRead]);
        $operator = $this->user('operator');

        $this->actingAs($operator)->post(route('settings.api-tokens.revoke', $token->id))
            ->assertRedirect(route('settings.api-tokens'));

        $this->assertTrue($token->fresh()->isRevoked());
        $this->assertNull(ApiToken::findActive($plaintext));
    }

    public function test_an_unknown_scope_is_rejected(): void
    {
        $this->actingAs($this->user('operator'))
            ->post(route('settings.api-tokens.store'), ['name' => 'x', 'scope' => 'leads:write'])
            ->assertSessionHasErrors('scope');

        $this->assertSame(0, ApiToken::count());
    }

    public function test_clients_cannot_see_or_manage_tokens(): void
    {
        [$token] = ApiToken::issue(Tenant::DEFAULT_ID, 'vigilo', [ApiTokenScope::LeadMetadataRead]);
        $client = $this->user('client');

        $this->actingAs($client)->get(route('settings.api-tokens'))->assertForbidden();
        $this->actingAs($client)->post(route('settings.api-tokens.store'), [
            'name' => 'x', 'scope' => ApiTokenScope::LeadMetadataRead->value,
        ])->assertForbidden();
        $this->actingAs($client)->post(route('settings.api-tokens.revoke', $token->id))->assertForbidden();

        $this->assertSame(1, ApiToken::count());
        $this->assertFalse($token->fresh()->isRevoked());
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('settings.api-tokens'))->assertRedirect(route('login'));
    }
}
