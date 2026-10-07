<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TwoFactor\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Second step of the login for users with 2FA enabled. LoginController has
 * already checked the password and parked the user id in the session; nobody
 * is authenticated until a valid TOTP or recovery code arrives here.
 *
 * Throttled per pending user (see AppServiceProvider::bootRateLimiters()).
 */
class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($this->pendingUser($request) === null) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request, TwoFactorAuthenticator $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'code'          => ['nullable', 'string', 'max:16'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $this->pendingUser($request);
        if ($user === null) {
            return redirect()->route('login')->with('warning', __('Your sign-in expired. Please enter your password again.'));
        }

        $code = trim((string) ($data['code'] ?? ''));
        $recovery = trim((string) ($data['recovery_code'] ?? ''));
        $usedRecovery = false;

        if ($code !== '' && ($step = $twoFactor->verifyCode($user, $code)) !== null) {
            $twoFactor->markCodeUsed($user, $step);
        } elseif ($recovery !== '' && $twoFactor->useRecoveryCode($user, $recovery)) {
            $usedRecovery = true;
        } else {
            Log::warning('lodgely.auth.two_factor_failed', ['user_id' => $user->id, 'ip' => $request->ip()]);

            throw ValidationException::withMessages([
                'code' => __('That code is not valid. Try again, or use a recovery code.'),
            ]);
        }

        $pending = $request->session()->pull(LoginController::PENDING_TWO_FACTOR);

        Auth::login($user, (bool) ($pending['remember'] ?? false));
        $request->session()->regenerate();

        Log::info('lodgely.auth.login', [
            'user_id'       => $user->id,
            'ip'            => $request->ip(),
            'two_factor'    => true,
            'recovery_code' => $usedRecovery,
        ]);

        if ($usedRecovery) {
            $left = count($user->two_factor_recovery_codes ?? []);
            session()->flash('two-factor.status', trans_choice(
                'You signed in with a recovery code. :count recovery code left. Regenerate them on your profile page.|You signed in with a recovery code. :count recovery codes left. Regenerate them on your profile page.',
                $left,
                ['count' => $left],
            ));

            return redirect()->route('profile');
        }

        return redirect()->intended(route('inbox'));
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(LoginController::PENDING_TWO_FACTOR);

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget(LoginController::PENDING_TWO_FACTOR);

            return null;
        }

        $user = User::find($pending['id'] ?? null);

        // Deactivated or 2FA reset in the meantime → start over at the password.
        if ($user === null || ! $user->is_active || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(LoginController::PENDING_TWO_FACTOR);

            return null;
        }

        return $user;
    }
}
