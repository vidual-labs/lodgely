<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public page at "/". Guests get the product landing page (with a
 * discreet "Sign in" link to /login); signed-in users, and every visitor on
 * installs that set LODGELY_LANDING_ENABLED=false, go straight to the inbox —
 * which in turn bounces guests to the login form, exactly as before.
 */
class LandingController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user() || ! config('lodgely.landing.enabled')) {
            return redirect()->route('inbox');
        }

        return view('landing.index');
    }
}
