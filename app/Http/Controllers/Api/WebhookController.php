<?php

namespace App\Http\Controllers\Api;

use App\Domain\Leads\Services\LeadIngestor;
use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    public function receive(string $token, Request $request, LeadIngestor $ingestor): JsonResponse
    {
        $endpoint = WebhookEndpoint::where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $endpoint) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        $data = $request->json()->all();

        if (! is_array($data)) {
            return response()->json(['error' => 'JSON body required.'], 422);
        }

        $validator = Validator::make($data, [
            'full_name'     => ['nullable', 'string', 'max:255'],
            'email'         => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'message'       => ['nullable', 'string', 'max:5000'],
            'client_name'   => ['nullable', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $fields = $validator->validated();

        if (empty($fields['email']) && empty($fields['phone'])) {
            return response()->json(['error' => 'At least one of email or phone is required.'], 422);
        }

        $import = Import::create([
            'tenant_id'      => $endpoint->tenant_id,
            'user_id'        => $endpoint->user_id,
            'source'         => 'webhook',
            'label'          => 'Webhook: '.$endpoint->label,
            'reference'      => $endpoint->token,
            'rows_total'     => 1,
            'rows_imported'  => 0,
            'rows_duplicate' => 0,
            'rows_invalid'   => 0,
            'meta'           => ['webhook_endpoint_id' => $endpoint->id],
            'started_at'     => now(),
        ]);

        $lead = $ingestor->ingest([
            'source'        => 'webhook',
            'client_name'   => $fields['client_name'] ?? $endpoint->default_client_name,
            'campaign_name' => $fields['campaign_name'] ?? $endpoint->default_campaign_name,
            'full_name'     => $fields['full_name'] ?? null,
            'email'         => $fields['email'] ?? null,
            'phone'         => $fields['phone'] ?? null,
            'message'       => $fields['message'] ?? null,
            'raw_payload'   => $data,
        ], $import, $endpoint->tenant_id, $endpoint->user_id);

        if ($lead->duplicate_flag) {
            $import->increment('rows_duplicate');
        } else {
            $import->increment('rows_imported');
        }

        $import->update(['finished_at' => now()]);
        $endpoint->update(['last_used_at' => now()]);

        return response()->json([
            'status'    => 'accepted',
            'lead_id'   => $lead->id,
            'duplicate' => $lead->duplicate_flag,
        ], 201);
    }
}
