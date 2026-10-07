<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginController;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TwoFactor\TwoFactorAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function makeUser(string $role = 'operator', array $overrides = []): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create(array_merge([
            'name'      => 'Olive',
            'email'     => 'olive@example.com',
            'password'  => Hash::make('correct-horse-battery-staple'),
            'role'      => $role,
            'is_active' => true,
        ], $overrides));
    }

    private function enrolledUser(string $role = 'operator'): User
    {
        $user = $this->makeUser($role);
        $user->forceFill([
            'two_factor_secret'         => self::SECRET,
            'two_factor_recovery_codes' => ['aaaaa-bbbbb', 'ccccc-ddddd'],
            'two_factor_confirmed_at'   => now(),
        ])->save();

        return $user->fresh();
    }

    private function currentCode(string $secret = self::SECRET): string
    {
        return (new Google2FA())->getCurrentOtp($secret);
    }

    private function loginWithPassword(): \Illuminate\Testing\TestResponse
    {
        return $this->post('/login', [
            'email'    => 'olive@example.com',
            'password' => 'correct-horse-battery-staple',
        ]);
    }

    // ── Login ────────────────────────────────────────────────────────────

    public function test_user_without_2fa_logs_in_directly(): void
    {
        $this->makeUser();

        $this->loginWithPassword()->assertRedirect(route('inbox'));
        $this->assertAuthenticated();
    }

    public function test_password_alone_does_not_log_in_a_2fa_user(): void
    {
        $this->enrolledUser();

        $this->loginWithPassword()->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        // The parked login must not open any authenticated page.
        $this->get('/inbox')->assertRedirect('/login');
    }

    public function test_valid_code_completes_login(): void
    {
        $user = $this->enrolledUser();
        $this->loginWithPassword();

        $this->get('/two-factor-challenge')->assertOk()->assertSee(__('Authentication code'));

        $this->post('/two-factor-challenge', ['code' => $this->currentCode()])
            ->assertRedirect(route('inbox'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->two_factor_last_used_step);
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->enrolledUser();
        $this->loginWithPassword();

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $user = $this->enrolledUser();
        $code = $this->currentCode();

        $this->loginWithPassword();
        $this->post('/two-factor-challenge', ['code' => $code]);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->loginWithPassword();

        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_works_exactly_once(): void
    {
        $user = $this->enrolledUser();

        $this->loginWithPassword();
        $this->post('/two-factor-challenge', ['recovery_code' => 'AAAAA-BBBBB'])
            ->assertRedirect(route('profile'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['ccccc-ddddd'], $user->fresh()->two_factor_recovery_codes);

        $this->post('/logout');
        $this->loginWithPassword();

        $this->post('/two-factor-challenge', ['recovery_code' => 'aaaaa-bbbbb'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_challenge_without_a_password_step_goes_back_to_login(): void
    {
        $this->enrolledUser();

        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
        $this->post('/two-factor-challenge', ['code' => $this->currentCode()])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_pending_login_expires(): void
    {
        $this->enrolledUser();
        $this->loginWithPassword();

        $this->travel(LoginController::PENDING_TWO_FACTOR_TTL_SECONDS + 1)->seconds();

        $this->post('/two-factor-challenge', ['code' => $this->currentCode()])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_finish_the_challenge(): void
    {
        $user = $this->enrolledUser();
        $this->loginWithPassword();

        $user->forceFill(['is_active' => false])->save();

        $this->post('/two-factor-challenge', ['code' => $this->currentCode()])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_challenge_is_throttled_per_user(): void
    {
        $this->enrolledUser();
        $this->loginWithPassword();

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
                ->post('/two-factor-challenge', ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->post('/two-factor-challenge', ['code' => $this->currentCode()])
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_2fa_stays_enforced_after_demotion_to_client(): void
    {
        $this->enrolledUser('client');

        $this->loginWithPassword()->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
    }

    // ── Enrolment (profile) ──────────────────────────────────────────────

    public function test_operator_can_enrol_and_gets_recovery_codes(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['remember_token' => 'old-token'])->save();

        $this->actingAs($user)
            ->post('/profile/two-factor', ['password' => 'correct-horse-battery-staple'])
            ->assertRedirect(route('profile'));

        $user->refresh();
        $this->assertTrue($user->hasPendingTwoFactorSetup());
        $this->assertFalse($user->hasTwoFactorEnabled());

        // Pending setup shows the QR code and the manual key.
        $this->get('/profile')->assertOk()->assertSee('<svg', false);

        $this->post('/profile/two-factor/confirm', ['code' => $this->currentCode($user->two_factor_secret)])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('two-factor.recovery-codes', fn ($codes) => count($codes) === TwoFactorAuthenticator::RECOVERY_CODE_COUNT);

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertNotSame('old-token', $user->remember_token);
    }

    public function test_secret_is_encrypted_at_rest(): void
    {
        $user = $this->enrolledUser();

        $raw = \DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertNotSame(self::SECRET, $raw);
        $this->assertStringNotContainsString(self::SECRET, (string) $raw);
    }

    public function test_enrolment_needs_the_current_password(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post('/profile/two-factor', ['password' => 'wrong'])
            ->assertSessionHasErrorsIn('twoFactor', 'password');

        $this->assertFalse($user->fresh()->hasPendingTwoFactorSetup());
    }

    public function test_wrong_confirmation_code_keeps_2fa_off(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post('/profile/two-factor', ['password' => 'correct-horse-battery-staple']);

        $this->post('/profile/two-factor/confirm', ['code' => '000000'])
            ->assertSessionHasErrorsIn('twoFactor', 'code');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_clients_cannot_enrol(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client)
            ->post('/profile/two-factor', ['password' => 'correct-horse-battery-staple'])
            ->assertForbidden();

        $this->get('/profile')->assertOk()->assertDontSee(__('Set up two-factor authentication'));
    }

    public function test_disabling_needs_password_and_a_code(): void
    {
        $user = $this->enrolledUser();
        $this->actingAs($user);

        $this->post('/profile/two-factor/disable', ['password' => 'correct-horse-battery-staple', 'code' => '000000'])
            ->assertSessionHasErrorsIn('twoFactor', 'code');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->post('/profile/two-factor/disable', ['password' => 'wrong', 'code' => $this->currentCode()])
            ->assertSessionHasErrorsIn('twoFactor', 'password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->post('/profile/two-factor/disable', ['password' => 'correct-horse-battery-staple', 'code' => $this->currentCode()])
            ->assertRedirect(route('profile'));

        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasTwoFactorEnabled());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
    }

    public function test_recovery_codes_can_be_regenerated(): void
    {
        $user = $this->enrolledUser();

        $this->actingAs($user)
            ->post('/profile/two-factor/recovery-codes', ['password' => 'correct-horse-battery-staple'])
            ->assertSessionHas('two-factor.recovery-codes');

        $codes = $user->fresh()->two_factor_recovery_codes;
        $this->assertCount(TwoFactorAuthenticator::RECOVERY_CODE_COUNT, $codes);
        $this->assertNotContains('aaaaa-bbbbb', $codes);
    }

    // ── CLI reset ────────────────────────────────────────────────────────

    public function test_cli_reset_turns_2fa_off(): void
    {
        $user = $this->enrolledUser();

        $this->artisan('lodgely:user:2fa-reset', ['email' => 'olive@example.com', '--force' => true])
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->loginWithPassword()->assertRedirect(route('inbox'));
    }
}
