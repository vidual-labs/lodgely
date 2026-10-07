<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Ai\Support\Pseudonymizer;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** Session key holding a password-verified login that still owes a 2FA code. */
    public const PENDING_TWO_FACTOR = 'login.two_factor';

    /** How long a pending 2FA login stays valid before the password must be re-entered. */
    public const PENDING_TWO_FACTOR_TTL_SECONDS = 600;

    public function show()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->boolean('remember');

        // validate() rather than attempt(): a user with 2FA must not be logged
        // in until the second factor checks out. Both are timeboxed by the
        // guard, so a miss takes as long as a hit (no account-existence oracle).
        if (! Auth::validate([...$credentials, 'is_active' => true])) {
            Log::warning('lodgely.auth.login_failed', [
                'email' => (new Pseudonymizer())->maskEmail(mb_strtolower(trim($credentials['email']))),
                'ip'    => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => __('Invalid credentials or account disabled.'),
            ]);
        }

        /** @var User $user */
        $user = Auth::getLastAttempted();
        Auth::getProvider()->rehashPasswordIfRequired($user, $credentials);

        $request->session()->regenerate();

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(self::PENDING_TWO_FACTOR, [
                'id'         => $user->id,
                'remember'   => $remember,
                'expires_at' => now()->addSeconds(self::PENDING_TWO_FACTOR_TTL_SECONDS)->getTimestamp(),
            ]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $remember);

        Log::info('lodgely.auth.login', ['user_id' => $user->id, 'ip' => $request->ip(), 'two_factor' => false]);

        return redirect()->intended(route('inbox'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
