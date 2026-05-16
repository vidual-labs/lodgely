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
 * Adapter for any endpoint that speaks OpenAI's chat-completions shape:
 * OpenAI itself, Together, Groq, LM Studio, vLLM, etc. The choice is
 * controlled entirely by `base_url` and an optional Bearer key.
 */
class OpenAiCompatibleProvider implements LlmProvider
{
    public function key(): string
    {
        return 'openai_compatible';
    }

    public function label(): string
    {
        return 'OpenAI-compatible (OpenAI, Together, Groq, LM Studio, …)';
    }

    public function ping(AiSetting $settings): bool
    {
        $baseUrl = $settings->effectiveBaseUrl();
        if (! $baseUrl) {
            return false;
        }

        try {
            // /models is the canonical low-cost endpoint for OpenAI-compatible APIs.
            $response = $this->client($settings)->get($baseUrl.'/models');

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
            throw new LlmCallException('Missing base URL or model for OpenAI-compatible provider.');
        }

        $body = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $request->system],
                ['role' => 'user',   'content' => $request->user],
            ],
        ];

        if ($request->temperature !== null) {
            $body['temperature'] = $request->temperature;
        }
        if ($request->maxTokens !== null) {
            $body['max_tokens'] = $request->maxTokens;
        }

        try {
            $response = $this->client($settings)->post($baseUrl.'/chat/completions', $body);
        } catch (Throwable $e) {
            throw new LlmCallException('Transport error: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new LlmCallException(
                sprintf('Provider returned %d: %s', $response->status(), substr($response->body(), 0, 400))
            );
        }

        $json = $response->json();
        $text = $json['choices'][0]['message']['content'] ?? null;

        if (! is_string($text) || $text === '') {
            throw new LlmCallException('Provider returned an unexpected payload shape.');
        }

        $usage = null;
        if (isset($json['usage']) && is_array($json['usage'])) {
            $usage = [
                'prompt'     => $json['usage']['prompt_tokens']     ?? null,
                'completion' => $json['usage']['completion_tokens'] ?? null,
                'total'      => $json['usage']['total_tokens']      ?? null,
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
