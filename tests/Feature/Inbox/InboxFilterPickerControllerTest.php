<?php

namespace Tests\Feature\Inbox;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Native HTML form endpoint for the filter-dropdown picker. Livewire-level
 * behaviour (defaults, toggling, persistence) is covered in InboxPageTest;
 * this covers the HTTP surface specific to the controller — validation, and
 * the redirect carrying/dropping filter state.
 */
class InboxFilterPickerControllerTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'operator'): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name' => 'U', 'email' => 'u@example.com', 'password' => Hash::make('p'),
            'role' => $role, 'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post('/inbox/filters', ['action' => 'apply', 'filters' => ['status']])
            ->assertRedirect('/login');
    }

    public function test_apply_persists_the_picked_set(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/inbox/filters', ['action' => 'apply', 'filters' => ['status', 'outreach']])
            ->assertRedirect();

        $this->assertSame(['status', 'outreach'], $user->fresh()->inbox_filters);
    }

    public function test_apply_with_no_filters_checked_persists_an_empty_set(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/inbox/filters', ['action' => 'apply'])
            ->assertRedirect();

        $this->assertSame([], $user->fresh()->inbox_filters);
    }

    public function test_apply_rejects_an_unknown_filter_key(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/inbox/filters', ['action' => 'apply', 'filters' => ['client']])
            ->assertSessionHasErrors('filters.0');

        $this->assertNull($user->fresh()->inbox_filters);
    }

    public function test_reset_clears_the_picked_filters_column(): void
    {
        $user = $this->user();
        $user->forceFill(['inbox_filters' => ['outreach']])->save();

        $this->actingAs($user)
            ->post('/inbox/filters', ['action' => 'reset'])
            ->assertRedirect();

        $this->assertNull($user->fresh()->inbox_filters);
    }

    /** @return array<string, string> */
    private function redirectQuery($response): array
    {
        $location = $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        ksort($params);

        return $params;
    }

    public function test_redirect_carries_the_hidden_filter_state_forward(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->post('/inbox/filters', [
            'action' => 'apply',
            'filters' => ['status', 'priority'],
            'search' => 'acme',
            'status' => 'new',
            'priority' => 'high',
            'sort' => 'priority_desc',
        ]);

        $response->assertRedirect();
        $expected = ['q' => 'acme', 'status' => 'new', 'priority' => 'high', 'sort' => 'priority_desc', 'filters' => '1'];
        ksort($expected);
        $this->assertSame($expected, $this->redirectQuery($response));
    }

    public function test_unchecking_a_filter_drops_its_value_from_the_redirect_even_if_the_hidden_input_still_has_one(): void
    {
        $user = $this->user();

        // Priority's hidden input still carries the pre-toggle value 'high'
        // (Livewire property state, unaware the checkbox was just
        // unchecked) — the controller must not let it leak into the query
        // string once 'priority' is no longer in the picked set, or the
        // list stays invisibly filtered by a dropdown that no longer shows.
        $response = $this->actingAs($user)->post('/inbox/filters', [
            'action' => 'apply',
            'filters' => ['status'],
            'status' => 'new',
            'priority' => 'high',
        ]);

        $response->assertRedirect();
        $expected = ['status' => 'new', 'filters' => '1'];
        ksort($expected);
        $this->assertSame($expected, $this->redirectQuery($response));
    }

    public function test_both_operators_and_clients_can_use_the_picker(): void
    {
        $client = $this->user('client');

        $this->actingAs($client)
            ->post('/inbox/filters', ['action' => 'apply', 'filters' => ['outreach']])
            ->assertRedirect();

        $this->assertSame(['outreach'], $client->fresh()->inbox_filters);
    }
}
