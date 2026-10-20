<?php

namespace App\Http\Controllers;

use App\Domain\Api\Enums\ApiTokenScope;
use App\Livewire\Settings\ApiTokensPage;
use App\Models\ApiToken;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Native form endpoints for {@see ApiTokensPage}: create and revoke. Operator
 * only. Revoking keeps the row (with `revoked_at`) so the request log's
 * token ids stay explainable.
 */
class ApiTokenController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'scope' => ['required', Rule::enum(ApiTokenScope::class)],
        ]);

        [$token, $plaintext] = ApiToken::issue(
            Tenant::DEFAULT_ID,
            trim($data['name']),
            [ApiTokenScope::from($data['scope'])],
            $request->user()->id,
        );

        Log::info('lodgely.api_token.created', ['api_token_id' => $token->id, 'user_id' => $request->user()->id]);

        return redirect()->route('settings.api-tokens')->with('api-tokens.created', $plaintext);
    }

    public function revoke(Request $request, int $token): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        $apiToken = ApiToken::where('tenant_id', Tenant::DEFAULT_ID)->findOrFail($token);

        if (! $apiToken->isRevoked()) {
            $apiToken->update(['revoked_at' => now()]);
            Log::info('lodgely.api_token.revoked', ['api_token_id' => $apiToken->id, 'user_id' => $request->user()->id]);
        }

        return redirect()->route('settings.api-tokens')->with(
            'api-tokens.notice',
            __('Token ":name" revoked. Tools using it can no longer read from lodgely.', ['name' => $apiToken->name]),
        );
    }
}
