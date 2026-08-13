<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Regression cover for the auth throttles.
 *
 * The point of these tests is the spoofing case. lodgely trusts every proxy by
 * default (TRUSTED_PROXIES='*', see bootstrap/app.php), so `$request->ip()` is
 * really "whatever the caller wrote in X-Forwarded-For". A throttle keyed only
 * on that value — which is what `throttle:5,1` gave us — is no throttle at all
 * against anyone willing to increment a header, and the install now holds real
 * client PII behind those logins.
 *
 * test_login_throttle_cannot_be_bypassed_by_spoofing_forwarded_for is the one
 * that matters: it fails against the old inline limiter and passes against the
 * email-keyed one.
 */
class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // The array cache store is per-application and so already fresh per
        // test, but clearing makes the intent explicit and keeps these tests
        // order-independent if the store ever changes.
        RateLimiter::clear('login:victim@example.com');
    }

    private function makeUser(array $overrides = []): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create(array_merge([
            'name' => 'Victim',
            'email' => 'victim@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
            'role' => 'operator',
            'is_active' => true,
        ], $overrides));
    }

    public function test_login_throttle_cannot_be_bypassed_by_spoofing_forwarded_for(): void
    {
        $this->makeUser();

        // Five wrong guesses, each from a different "client IP". Under an
        // IP-keyed limiter every one of these lands in its own bucket and the
        // attacker never runs out of attempts.
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', [
                'email' => 'victim@example.com',
                'password' => 'wrong-guess-'.$i,
            ], ['X-Forwarded-For' => "203.0.113.{$i}"]);
        }

        $response = $this->post('/login', [
            'email' => 'victim@example.com',
            'password' => 'wrong-guess-6',
        ], ['X-Forwarded-For' => '203.0.113.99']);

        $response->assertStatus(429);
    }

    public function test_throttling_one_account_does_not_lock_out_another(): void
    {
        $this->makeUser();
        $other = $this->makeUser(['email' => 'bystander@example.com', 'name' => 'Bystander']);

        for ($i = 1; $i <= 6; $i++) {
            $this->post('/login', [
                'email' => 'victim@example.com',
                'password' => 'wrong-guess-'.$i,
            ], ['X-Forwarded-For' => "203.0.113.{$i}"]);
        }

        // A different account from a different address still gets through: the
        // email bucket is exhausted, the wider IP bucket is not.
        $this->post('/login', [
            'email' => $other->email,
            'password' => 'correct-horse-battery-staple',
        ], ['X-Forwarded-For' => '198.51.100.7'])->assertRedirect(route('inbox'));

        $this->assertAuthenticatedAs($other);
    }

    public function test_successful_login_still_works_within_the_limit(): void
    {
        $user = $this->makeUser();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery-staple',
        ])->assertRedirect(route('inbox'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_reset_requests_are_throttled_per_email(): void
    {
        $this->makeUser();

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/forgot-password', [
                'email' => 'victim@example.com',
            ], ['X-Forwarded-For' => "203.0.113.{$i}"]);
        }

        $this->post('/forgot-password', [
            'email' => 'victim@example.com',
        ], ['X-Forwarded-For' => '203.0.113.99'])->assertStatus(429);
    }
}
