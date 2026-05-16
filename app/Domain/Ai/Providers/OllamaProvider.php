<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Contracts\LlmProvider;
use App\Domain\Ai\DTOs\LlmRequest;
use App\Domain\Ai\DTOs\LlmResponse;
use App\Domain\Ai\Exceptions\LlmCallException;
use App\Models\AiSetting;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Adapter for a local (or remote) Ollama server. Hits `/api/chat` with
 * stream:false so we get a single complete response. An optional Bearer
 * key is supported for Ollama proxies that require authentication.
 */
class OllamaProvider implements LlmProvider
{
    public function key(): string
    {
        return 'ollama';
    }

    public function label(): string
    {
        return 'Ollama (local or self-hosted)';
    }

    public function ping(AiSetting $settings): bool
    {
        $baseUrl = $settings->effectiveBaseUrl();
        if (! $baseUrl) {
            return false;
        }

        try {
            $response = $this->client($settings)->get($baseUrl.'/api/tags');

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function complete(LlmRequest $request, AiSetting $settings): LlmResponse
    {
        $baseUrl = $settings->effectiveBaseUrl();
        $model   = $settings->effectiveModel();

        if (! $baseUrl || ! $model) {
            throw new LlmCallException('Missing base URL or model for Ollama provider.');
        }

        $body = [
            'model'    => $model,
            'stream'   => false,
            'messages' => [
                ['role' => 'system', 'content' => $request->system],
                ['role' => 'user',   'content' => $request->user],
            ],
        ];

        $options = [];
        if ($request->temperature !== null) {
            $options['temperature'] = $request->temperature;
        }
        if ($request->maxTokens !== null) {
            $options['num_predict'] = $request->maxTokens;
        }
        if ($options !== []) {
            $body['options'] = $options;
        }

        try {
            $response = $this->client($settings)->post($baseUrl.'/api/chat', $body);
        } catch (Throwable $e) {
            throw new LlmCallException('Transport error: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new LlmCallException(
                sprintf('Ollama returned %d: %s', $response->status(), substr($response->body(), 0, 400))
            );
        }

        $json = $response->json();
        $text = $json['message']['content'] ?? null;

        if (! is_string($text) || $text === '') {
            throw new LlmCallException('Ollama returned an unexpected payload shape.');
        }

        $usage = null;
        if (isset($json['prompt_eval_count']) || isset($json['eval_count'])) {
            $prompt     = (int) ($json['prompt_eval_count'] ?? 0);
            $completion = (int) ($json['eval_count']        ?? 0);
            $usage = [
                'prompt'     => $prompt,
                'completion' => $completion,
                'total'      => $prompt + $completion,
            ];
        }

        return new LlmResponse(
            text: $text,
            model: (string) ($json['model'] ?? $model),
            tokenUsage: $usage,
        );
    }

    private function client(AiSetting $settings)
    {
        $http = Http::timeout((int) config('lodgely.ai.request_timeout_sec', 60))
            ->acceptJson()
            ->asJson();

        $apiKey = $settings->apiKey();
        if ($apiKey) {
            $http = $http->withToken($apiKey);
        }

        return $http;
    }
}
