<?php

namespace Tests\Support;

use App\Domain\Ai\Contracts\LlmProvider;
use App\Domain\Ai\DTOs\LlmRequest;
use App\Domain\Ai\DTOs\LlmResponse;
use App\Domain\Ai\Exceptions\LlmCallException;
use App\Models\AiSetting;

/**
 * Test double for LlmProvider. Records calls, returns a canned response.
 * Bound into the container in feature tests by binding the concrete
 * OpenAiCompatibleProvider class to this implementation.
 */
class FakeLlmProvider implements LlmProvider
{
    public array $calls = [];
    public ?LlmResponse $cannedResponse = null;
    public bool $pingResult = true;
    public bool $shouldFail = false;
    public string $failMessage = 'Synthetic provider failure';

    public function key(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Fake provider';
    }

    public function ping(AiSetting $settings): bool
    {
        return $this->pingResult;
    }

    public function complete(LlmRequest $request, AiSetting $settings): LlmResponse
    {
        $this->calls[] = compact('request', 'settings');

        if ($this->shouldFail) {
            throw new LlmCallException($this->failMessage);
        }

        return $this->cannedResponse ?? new LlmResponse(
            text: "## Summary\nAll signals look healthy.\n\n## Evaluation\n- Good clicks\n- Cost stable\n- Volume unclear\n\n## Suggested follow-ups\n- Test new audience\n",
            model: 'fake-model',
            tokenUsage: ['prompt' => 10, 'completion' => 20, 'total' => 30],
        );
    }
}
