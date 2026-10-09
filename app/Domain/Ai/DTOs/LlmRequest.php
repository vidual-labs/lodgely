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

    /** The on-disk shape of ai_summaries.prompt — what actually went to the model. */
    public function toStoredPrompt(): string
    {
        return "[SYSTEM]\n".$this->system."\n\n[USER]\n".$this->user;
    }

    /**
     * Rebuild the request from ai_summaries.prompt ("[SYSTEM]\n…\n\n[USER]\n…").
     * Kept this way so the exact disclosure is auditable and retries don't
     * depend on re-running the data assemblers. A blob without the markers is
     * treated as a bare user message.
     */
    public static function fromStoredPrompt(string $blob, ?float $temperature = null, ?int $maxTokens = null): self
    {
        if (preg_match('/^\[SYSTEM\]\n(.*?)\n\n\[USER\]\n(.*)$/s', $blob, $m)) {
            return new self(system: $m[1], user: $m[2], temperature: $temperature, maxTokens: $maxTokens);
        }

        return new self(system: '', user: $blob, temperature: $temperature, maxTokens: $maxTokens);
    }
}
