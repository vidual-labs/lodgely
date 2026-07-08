<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserDeactivationSessionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'operator', array $overrides = []): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create(array_merge([
            'name'      => 'Sam',
            'email'     => 'sam@example.com',
            'password'  => Hash::make('initial-password-123'),
            'role'      => $role,
            'is_active' => true,
        ], $overrides));
    }

    public function test_active_user_can_browse(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get('/inbox')->assertOk();
    }

    public function test_deactivated_user_is_signed_out_on_next_request(): void
    {
        $user = $this->makeUser();

        // Simulate an existing session, then deactivation mid-session.
        $this->actingAs($user)->get('/inbox')->assertOk();
        $user->forceFill(['is_active' => false])->save();

        $this->get('/inbox')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivated_client_is_signed_out_too(): void
    {
        $user = $this->makeUser('client', ['email' => 'client@example.com', 'is_active' => false]);

        $this->actingAs($user)->get('/my-reports')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_change_invalidates_sessions_carrying_the_old_hash(): void
    {
        $user = $this->makeUser();

        // First request stores the current password hash in the session
        // (AuthenticateSession middleware).
        $this->actingAs($user)->get('/inbox')->assertOk();

        // Another device changes the password.
        $user->forceFill(['password' => Hash::make('a-brand-new-password')])->save();

        // This session still carries the old hash and must be terminated.
        $this->get('/inbox')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
