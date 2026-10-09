<?php

namespace Tests\Feature;

use App\Livewire\Settings\ProfilePage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'operator', array $overrides = []): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create(array_merge([
            'name'      => 'Sam',
            'email'     => 'sam@example.com',
            'password'  => Hash::make('initial-password'),
            'role'      => $role,
            'is_active' => true,
            'locale'    => 'en',
            'ui_theme'  => 'light',
        ], $overrides));
    }

    public function test_guest_cannot_view_profile_page(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_user_can_update_profile_basics(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(ProfilePage::class)
            ->set('profile.name', 'Samantha')
            ->set('profile.email', 'samantha@example.com')
            ->set('profile.locale', 'de')
            ->set('profile.theme', 'dark')
            ->set('profile.current_password', 'initial-password')
            ->call('saveProfile')
            ->assertHasNoErrors()
            ->assertSet('profile.current_password', '');

        $fresh = $user->fresh();
        $this->assertSame('Samantha', $fresh->name);
        $this->assertSame('samantha@example.com', $fresh->email);
        $this->assertSame('de', $fresh->locale);
        $this->assertSame('dark', $fresh->ui_theme);
    }

    public function test_changing_email_requires_current_password(): void
    {
        $user = $this->makeUser();

        // A stolen session must not be able to swap in the attacker's address
        // and then take the account over through "forgot password".
        Livewire::actingAs($user)
            ->test(ProfilePage::class)
            ->set('profile.email', 'attacker@example.com')
            ->set('profile.current_password', 'wrong-password')
            ->call('saveProfile')
            ->assertHasErrors('profile.current_password');

        $this->assertSame('sam@example.com', $user->fresh()->email);
    }

    public function test_other_profile_fields_do_not_need_the_password(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(ProfilePage::class)
            ->set('profile.name', 'Samantha')
            ->set('profile.email', 'SAM@example.com')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertSame('Samantha', $user->fresh()->name);
    }

    public function test_email_must_remain_unique(): void
    {
        $this->makeUser('operator', ['email' => 'taken@example.com']);
        $other = $this->makeUser('client', ['email' => 'other@example.com', 'name' => 'Other']);

        Livewire::actingAs($other)
            ->test(ProfilePage::class)
            ->set('profile.email', 'taken@example.com')
            ->call('saveProfile')
            ->assertHasErrors('profile.email');
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(ProfilePage::class)
            ->set('password.current', 'initial-password')
            ->set('password.new', 'brand-new-password-1')
            ->set('password.confirmation', 'brand-new-password-1')
            ->call('changePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-password-1', $user->fresh()->password));
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(ProfilePage::class)
            ->set('password.current', 'wrong-password')
            ->set('password.new', 'brand-new-password-1')
            ->set('password.confirmation', 'brand-new-password-1')
            ->call('changePassword')
            ->assertHasErrors('password.current');

        $this->assertTrue(Hash::check('initial-password', $user->fresh()->password));
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(ProfilePage::class)
            ->set('password.current', 'initial-password')
            ->set('password.new', 'brand-new-password-1')
            ->set('password.confirmation', 'mismatched-confirmation-1')
            ->call('changePassword')
            ->assertHasErrors('password.confirmation');
    }

    public function test_client_role_can_use_the_profile_page(): void
    {
        $client = $this->makeUser('client', ['email' => 'client@example.com', 'name' => 'Client']);

        Livewire::actingAs($client)
            ->test(ProfilePage::class)
            ->set('profile.name', 'Renamed Client')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertSame('Renamed Client', $client->fresh()->name);
    }

    private function enableRanking(): void
    {
        config()->set('lodgely.ai.enabled', true);
        $row = \App\Models\AiSetting::forTenant(Tenant::DEFAULT_ID);
        $row->enabled = true;
        $row->provider = 'openai_compatible';
        $row->kinds_enabled = ['lead_ranking' => true];
        $row->lead_data_consent = true;
        $row->save();
    }

    public function test_client_saves_ranking_profile_for_own_scope_only(): void
    {
        $client = $this->makeUser('client');
        \App\Models\UserLeadScope::create(['user_id' => $client->id, 'tenant_id' => Tenant::DEFAULT_ID, 'client_name' => 'Acme']);
        $this->enableRanking();

        $component = Livewire::actingAs($client)->test(ProfilePage::class);
        $this->assertSame([['client_name' => 'Acme', 'profile' => '']], $component->get('rankingProfiles'));
        $this->assertStringContainsString('AI ranking profile', $component->html());

        $component->set('rankingProfiles.0.profile', 'Dinners for 20-30 guests, 40 EUR per person.')
            ->call('saveRankingProfiles')
            ->assertHasNoErrors();

        $this->assertSame(
            'Dinners for 20-30 guests, 40 EUR per person.',
            \App\Models\ClientAiProfile::textFor(Tenant::DEFAULT_ID, 'acme'),
        );
        $this->assertSame($client->id, \App\Models\ClientAiProfile::findFor(Tenant::DEFAULT_ID, 'Acme')->updated_by);

        // A tampered client_name outside the user's scopes is refused and nothing is written.
        Livewire::actingAs($client)->test(ProfilePage::class)
            ->set('rankingProfiles.0.client_name', 'Northwind')
            ->set('rankingProfiles.0.profile', 'hijack')
            ->call('saveRankingProfiles')
            ->assertForbidden();
        $this->assertNull(\App\Models\ClientAiProfile::textFor(Tenant::DEFAULT_ID, 'Northwind'));
    }

    public function test_operator_has_no_ranking_profile_card(): void
    {
        $op = $this->makeUser('operator');
        $this->enableRanking();

        $component = Livewire::actingAs($op)->test(ProfilePage::class);
        $this->assertSame([], $component->get('rankingProfiles'));
        $this->assertStringNotContainsString('AI ranking profile', $component->html());
        $component->call('saveRankingProfiles')->assertForbidden();
    }
}
