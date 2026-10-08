<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TwoFactor\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Optional 2FA management on the profile page (operators only).
 *
 * Plain POST forms → redirect back to /profile with a one-shot flash, per the
 * "native HTML, not Livewire actions" rail in CLAUDE.md. Validation errors
 * land in the `twoFactor` error bag so they don't collide with the Livewire
 * profile/password forms on the same page.
 *
 * Every step that changes the security posture asks for the current password,
 * so a hijacked session alone cannot enrol its own authenticator (locking the
 * owner out) or strip the second factor.
 */
class TwoFactorController extends Controller
{
    /** Step 1: generate a secret + recovery codes. Not enforced until confirmed. */
    public function enable(Request $request, TwoFactorAuthenticator $twoFactor): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($user->canEnableTwoFactor(), 403);

        if ($user->hasTwoFactorEnabled()) {
            return $this->back();
        }

        $this->assertPassword($request, $user);

        $user->forceFill([
            'two_factor_secret'          => $twoFactor->generateSecret(),
            'two_factor_recovery_codes'  => $twoFactor->generateRecoveryCodes(),
            'two_factor_confirmed_at'    => null,
            'two_factor_last_used_step'  => null,
        ])->save();

        Log::info('lodgely.auth.two_factor_setup_started', ['user_id' => $user->id]);

        return $this->back();
    }

    /** Step 2: prove the authenticator app works, then start enforcing 2FA. */
    public function confirm(Request $request, TwoFactorAuthenticator $twoFactor): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($user->canEnableTwoFactor(), 403);

        if (! $user->hasPendingTwoFactorSetup()) {
            return $this->back();
        }

        $request->validateWithBag('twoFactor', ['code' => ['required', 'string', 'max:16']]);

        $step = $twoFactor->verifyCode($user, (string) $request->input('code'));
        if ($step === null) {
            throw ValidationException::withMessages([
                'code' => __('That code is not valid. Check the time on your phone and try again.'),
            ])->errorBag('twoFactor');
        }

        $user->forceFill([
            'two_factor_confirmed_at'   => now(),
            'two_factor_last_used_step' => $step,
            // Remember-me cookies issued before 2FA existed would otherwise
            // keep working without a second factor.
            'remember_token'            => Str::random(60),
        ])->save();

        Log::info('lodgely.auth.two_factor_enabled', ['user_id' => $user->id]);

        return $this->back(
            __('Two-factor authentication is on. Store these recovery codes somewhere safe.'),
            $user->two_factor_recovery_codes ?? [],
        );
    }

    /** Abandon an unconfirmed setup (nothing is enforced yet, so no password needed). */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        if ($user->hasPendingTwoFactorSetup()) {
            app(TwoFactorAuthenticator::class)->disable($user);
        }

        return $this->back();
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorAuthenticator $twoFactor): RedirectResponse
    {
        $user = $this->user($request);

        if (! $user->hasTwoFactorEnabled()) {
            return $this->back();
        }

        $this->assertPassword($request, $user);

        $codes = $twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        Log::info('lodgely.auth.two_factor_recovery_codes_regenerated', ['user_id' => $user->id]);

        return $this->back(__('New recovery codes generated. The old ones no longer work.'), $codes);
    }

    /** Turning 2FA off needs the password and a current code (or recovery code). */
    public function disable(Request $request, TwoFactorAuthenticator $twoFactor): RedirectResponse
    {
        $user = $this->user($request);

        if (! $user->hasTwoFactorEnabled()) {
            return $this->back();
        }

        $this->assertPassword($request, $user);
        $request->validateWithBag('twoFactor', ['code' => ['required', 'string', 'max:32']]);

        $code = (string) $request->input('code');
        if ($twoFactor->verifyCode($user, $code) === null && ! $twoFactor->useRecoveryCode($user, $code)) {
            throw ValidationException::withMessages([
                'code' => __('That code is not valid.'),
            ])->errorBag('twoFactor');
        }

        $twoFactor->disable($user);

        Log::warning('lodgely.auth.two_factor_disabled', ['user_id' => $user->id, 'by' => 'self']);

        return $this->back(__('Two-factor authentication is off.'));
    }

    private function assertPassword(Request $request, User $user): void
    {
        $request->validateWithBag('twoFactor', ['password' => ['required', 'string']]);

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('Current password is incorrect.'),
            ])->errorBag('twoFactor');
        }
    }

    /** @param list<string>|null $recoveryCodes shown once, then gone from the session */
    private function back(?string $status = null, ?array $recoveryCodes = null): RedirectResponse
    {
        $redirect = redirect()->route('profile');

        if ($status !== null) {
            $redirect->with('two-factor.status', $status);
        }
        if ($recoveryCodes !== null) {
            $redirect->with('two-factor.recovery-codes', $recoveryCodes);
        }

        return $redirect;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
