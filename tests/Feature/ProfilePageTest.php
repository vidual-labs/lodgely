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
            ->call('saveProfile')
            ->assertHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame('Samantha', $fresh->name);
        $this->assertSame('samantha@example.com', $fresh->email);
        $this->assertSame('de', $fresh->locale);
        $this->assertSame('dark', $fresh->ui_theme);
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
}
