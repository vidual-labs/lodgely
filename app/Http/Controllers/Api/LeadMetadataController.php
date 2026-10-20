<?php

namespace App\Http\Controllers\Api;

use App\Domain\Leads\Services\LeadMetadataFeed;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateApiToken;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * `GET /api/v1/lead-metadata` — the read-only change feed vigilo polls
 * (LG-15). Lead *metadata* only; see {@see LeadMetadataFeed} for what goes
 * out and why nothing personal can.
 */
class LeadMetadataController extends Controller
{
    public function index(Request $request, LeadMetadataFeed $feed): JsonResponse
    {
        /** @var ApiToken $token */
        $token = $request->attributes->get(AuthenticateApiToken::ATTRIBUTE);

        $validator = Validator::make($request->query(), [
            'client'        => ['required', 'string', 'max:255'],
            'cursor'        => ['nullable', 'string', 'max:512'],
            'updated_since' => ['nullable', 'date'],
            'limit'         => ['nullable', 'integer', 'min:1', 'max:'.LeadMetadataFeed::MAX_LIMIT],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $params = $validator->validated();
        $clientKey = (string) $params['client'];
        $cursor = isset($params['cursor']) && $params['cursor'] !== '' ? (string) $params['cursor'] : null;

        $after = null;
        if ($cursor !== null) {
            $after = LeadMetadataFeed::decodeCursor($cursor);
            if ($after === null) {
                return response()->json(['message' => 'Invalid cursor.'], 400);
            }
        }

        $clientNames = $feed->clientNamesForKey($token->tenant_id, $clientKey);
        if ($clientNames === []) {
            return response()->json(['message' => 'Unknown client.'], 404);
        }

        $page = $feed->page(
            tenantId: $token->tenant_id,
            clientNames: $clientNames,
            after: $after,
            updatedSince: isset($params['updated_since']) ? Carbon::parse($params['updated_since']) : null,
            limit: (int) ($params['limit'] ?? LeadMetadataFeed::MAX_LIMIT),
            givenCursor: $cursor,
        );

        // Who read how much — never what.
        Log::info('lodgely.api.lead_metadata', [
            'api_token_id' => $token->id,
            'client'       => $clientKey,
            'rows'         => count($page['data']),
            'has_more'     => $page['has_more'],
        ]);

        return response()->json([
            'contract_version' => LeadMetadataFeed::CONTRACT_VERSION,
            'client'           => $clientKey,
            'data'             => $page['data'],
            'next_cursor'      => $page['next_cursor'],
            'has_more'         => $page['has_more'],
        ]);
    }
}
