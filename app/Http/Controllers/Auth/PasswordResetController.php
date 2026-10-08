<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Ai\Support\Pseudonymizer;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

use function Illuminate\Support\defer;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:160'],
        ]);

        $email = mb_strtolower(trim((string) $request->input('email')));

        // Masked, not raw: these lines go to stdout → the Docker log driver →
        // wherever the host ships logs, which is outside the retention regime
        // that governs the rest of our personal data. The masked form is still
        // enough to correlate a support report with a log line.
        $maskedEmail = (new Pseudonymizer())->maskEmail($email);

        // Deferred until after the response is sent: whether a user exists
        // decides whether we talk to the mail server, and an SMTP round-trip
        // is far slower than the broker's 200 ms timebox — answering only
        // once it finished would turn response time into an
        // "is this email registered?" oracle.
        defer(static function () use ($email, $maskedEmail): void {
            $user = User::where('email', $email)->first();
            if ($user && ! $user->is_active) {
                // Don't issue tokens for disabled accounts.
                Log::info('lodgely.password.reset_request.skipped_inactive', ['email' => $maskedEmail]);

                return;
            }

            try {
                $status = Password::sendResetLink(['email' => $email]);
            } catch (Throwable $e) {
                // Runs after the response, so nobody would see this otherwise.
                report($e);

                return;
            }

            Log::info('lodgely.password.reset_request', [
                'email'  => $maskedEmail,
                'status' => $status,
            ]);
        });

        return back()->with('status', __('If that email matches an active account, a reset link is on its way.'));
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                if (! $user->is_active) {
                    throw ValidationException::withMessages([
                        'email' => __('This account is disabled. Ask an operator to re-enable it.'),
                    ]);
                }

                $user->forceFill([
                    'password'       => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                Log::info('lodgely.password.reset', ['id' => $user->id]);
            }
        );

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        if ($status !== Password::PASSWORD_RESET) {
            // One message for "no such user" and "bad token" alike. The
            // broker's own strings tell them apart, which made this form an
            // account-enumeration oracle (POST any token + a guessed email).
            throw ValidationException::withMessages([
                'email' => [__('This password reset link is invalid or has expired.')],
            ]);
        }

        return redirect()->route('login')->with('status', __('Password updated. You can sign in now.'));
    }
}
