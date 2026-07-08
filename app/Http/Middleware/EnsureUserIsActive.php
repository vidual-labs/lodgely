<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out authenticated users whose account has been deactivated.
 *
 * `is_active` was previously only checked at login (LoginController passes it
 * to Auth::attempt), so deactivating a user did nothing to their existing
 * session or remember-me cookie — they could keep using the app until they
 * happened to log out. This middleware closes that gap: on the next request
 * after deactivation the session is invalidated and the user lands on the
 * login screen, where the login-time check keeps them out.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Log::info('lodgely.auth.inactive_session_terminated', ['user_id' => $user->id]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, __('This account has been deactivated.'));
            }

            return redirect()->route('login')->with(
                'status',
                __('This account has been deactivated. Contact an operator if you think this is a mistake.'),
            );
        }

        return $next($request);
    }
}
