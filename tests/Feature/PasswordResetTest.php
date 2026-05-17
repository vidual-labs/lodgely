<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create(array_merge([
            'name'      => 'Pat',
            'email'     => 'pat@example.com',
            'password'  => Hash::make('initial-password'),
            'role'      => 'operator',
            'is_active' => true,
        ], $overrides));
    }

    public function test_request_form_renders(): void
    {
        $this->withoutVite()
            ->get('/forgot-password')
            ->assertOk()
            ->assertSee('Reset your password');
    }

    public function test_active_user_receives_reset_link(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_inactive_user_does_not_receive_reset_link(): void
    {
        Notification::fake();
        $user = $this->makeUser(['is_active' => false]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSentTo($user);
    }

    public function test_unknown_email_does_not_leak_existence(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = $this->makeUser();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'a-brand-new-password-1',
            'password_confirmation' => 'a-brand-new-password-1',
        ])->assertRedirect(route('login'))
          ->assertSessionHas('status');

        $this->assertTrue(Hash::check('a-brand-new-password-1', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = $this->makeUser();

        $response = $this->post('/reset-password', [
            'token'                 => 'definitely-not-valid',
            'email'                 => $user->email,
            'password'              => 'a-brand-new-password-1',
            'password_confirmation' => 'a-brand-new-password-1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('initial-password', $user->fresh()->password));
    }

    public function test_password_must_meet_minimum_length(): void
    {
        $user  = $this->makeUser();
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'too-short',
            'password_confirmation' => 'too-short',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('initial-password', $user->fresh()->password));
    }

    public function test_inactive_user_cannot_complete_reset(): void
    {
        $user  = $this->makeUser(['is_active' => false]);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'a-brand-new-password-1',
            'password_confirmation' => 'a-brand-new-password-1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('initial-password', $user->fresh()->password));
    }
}
