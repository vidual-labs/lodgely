<?php

namespace App\Domain\Ai\DTOs;

/**
 * Provider-agnostic request shape. Both OpenAI-compatible and Ollama
 * endpoints accept a system + user message pair, so we keep the DTO
 * narrow.
 */
final class LlmRequest
{
    public function __construct(
        public readonly string $system,
        public readonly string $user,
        public readonly ?float $temperature = null,
        public readonly ?int $maxTokens = null,
    ) {
    }
}
