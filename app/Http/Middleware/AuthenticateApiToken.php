<?php

namespace App\Http\Middleware;

use App\Domain\Api\Enums\ApiTokenScope;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the read API: `api.token:<scope>`.
 *
 * 401 for a missing, unknown or revoked token; 403 for a valid token without
 * the scope. The token is put on the request as the `api_token` attribute for
 * the controller. This guard is separate from the session guard on purpose —
 * an API token never logs anyone in.
 */
class AuthenticateApiToken
{
    public const ATTRIBUTE = 'api_token';

    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $token = ApiToken::findActive((string) $request->bearerToken());

        if ($token === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $token->allows(ApiTokenScope::from($scope))) {
            return response()->json(['message' => 'This token lacks the '.$scope.' scope.'], 403);
        }

        // One write per minute at most; the timestamp is for the settings page.
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set(self::ATTRIBUTE, $token);

        return $next($request);
    }
}
