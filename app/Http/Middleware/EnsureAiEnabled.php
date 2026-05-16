<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aborts with 404 when AI is disabled at the application level. Wrapped
 * around the /settings/ai and /ai/drafts routes so the URLs simply don't
 * exist when LODGELY_AI_ENABLED=false.
 */
class EnsureAiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('lodgely.ai.enabled'), 404);

        return $next($request);
    }
}
