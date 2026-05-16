<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\DTOs\LlmRequest;
use App\Domain\Ai\DTOs\LlmResponse;
use App\Models\AiSetting;

/**
 * Adapter for an LLM backend. New providers (Anthropic, Mistral, etc.)
 * implement this interface and are registered in
 * AppServiceProvider::LLM_PROVIDERS.
 *
 * Both built-in implementations (OpenAI-compatible and Ollama) talk JSON
 * over HTTP via the Http facade — no extra SDK dependency.
 */
interface LlmProvider
{
    /** Stable key used as `ai_settings.provider` value. */
    public function key(): string;

    /** Human-readable label shown in the settings UI. */
    public function label(): string;

    /**
     * Cheap connectivity check. Should not consume tokens. Returns true on
     * any 2xx response from a trivial endpoint, false otherwise.
     */
    public function ping(AiSetting $settings): bool;

    /**
     * Run a single chat-completion. Throws LlmCallException on transport
     * errors, non-2xx responses, or unexpected payload shape.
     */
    public function complete(LlmRequest $request, AiSetting $settings): LlmResponse;
}
