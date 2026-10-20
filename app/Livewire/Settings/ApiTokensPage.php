<?php

namespace App\Livewire\Settings;

use App\Domain\Api\Enums\ApiTokenScope;
use App\Http\Controllers\ApiTokenController;
use App\Models\ApiToken;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Settings → API tokens. Renders only: creating and revoking a token are
 * native form posts to {@see ApiTokenController}, the same rail as the
 * backups page (see CLAUDE.md on silently dropped Livewire clicks). A new
 * token's plaintext arrives once, as a session flash, and is never stored.
 */
#[Layout('components.layouts.app')]
class ApiTokensPage extends Component
{
    public ?string $createdToken = null;

    public ?string $notice = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $this->createdToken = session('api-tokens.created');
        $this->notice = session('api-tokens.notice');
    }

    public function render(): View
    {
        return view('livewire.settings.api-tokens-page', [
            'tokens' => ApiToken::query()
                ->where('tenant_id', Tenant::DEFAULT_ID)
                ->with('user:id,name')
                ->orderByRaw('revoked_at IS NOT NULL')
                ->orderByDesc('created_at')
                ->get(),
            'scopes' => ApiTokenScope::cases(),
        ]);
    }
}
