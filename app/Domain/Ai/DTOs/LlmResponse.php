<?php

namespace App\Domain\Ai\DTOs;

final class LlmResponse
{
    /**
     * @param  array<string, mixed>|null  $tokenUsage  e.g. ['prompt'=>X, 'completion'=>Y, 'total'=>Z]
     */
    public function __construct(
        public readonly string $text,
        public readonly string $model,
        public readonly ?array $tokenUsage = null,
    ) {
    }
}
