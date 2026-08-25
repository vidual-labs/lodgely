<?php

namespace App\Livewire\Users;

use App\Domain\Leads\Enums\ClientType;
use App\Domain\Leads\Enums\UserRole;
use App\Models\User;
use App\Models\UserLeadScope;
use App\Support\Like;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class UsersPage extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $roleFilter = '';

    public bool $showForm = false;

    public ?int $editingUserId = null;

    public ?string $generatedPassword = null;

    /** @var array<string, mixed> */
    public array $form = [
        'name' => '',
        'email' => '',
        'role' => 'operator',
        'client_type' => '',
        'password' => '',
        'is_active' => true,
        'scopes_input' => '',
    ];

    public function mount(): void
    {
        $this->guardOperator();
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'roleFilter'], true)) {
            $this->resetPage();
        }
    }

    // ------------------------------------------------------------------ form

    public function openCreate(): void
    {
        $this->guardOperator();
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->guardOperator();
        $user = User::with('leadScopes')->findOrFail($id);

        $this->editingUserId = $user->id;
        $this->form = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'client_type' => $user->client_type?->value ?? '',
            'password' => '',
            'is_active' => (bool) $user->is_active,
            'scopes_input' => $user->leadScopes->pluck('client_name')->implode(', '),
        ];
        $this->generatedPassword = null;
        $this->showForm = true;
    }

    public function close(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function generatePassword(): void
    {
        $this->generatedPassword = Str::password(16);
        $this->form['password'] = $this->generatedPassword;
    }

    public function save(): void
    {
        $this->guardOperator();

        $editing = $this->editingUserId !== null;

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:120'],
            'form.email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($this->editingUserId)],
            'form.role' => ['required', Rule::in(['operator', 'client'])],
            'form.client_type' => ['nullable', Rule::in(array_merge([''], array_column(ClientType::cases(), 'value')))],
            'form.password' => [$editing ? 'nullable' : 'required', 'string', 'min:12'],
            'form.is_active' => ['boolean'],
            'form.scopes_input' => ['nullable', 'string', 'max:2000'],
        ])['form'];

        if ($editing && $this->editingUserId === auth()->id()) {
            if ($data['role'] !== UserRole::Operator->value) {
                $this->addError('form.role', __('You cannot remove your own operator role.'));

                return;
            }
            if (! $data['is_active']) {
                $this->addError('form.is_active', __('You cannot deactivate yourself.'));

                return;
            }
        }

        $payload = [
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'role' => $data['role'],
            'client_type' => $data['role'] === UserRole::Client->value && $data['client_type'] !== ''
                ? $data['client_type']
                : null,
            'is_active' => (bool) $data['is_active'],
        ];
        if ($data['password'] !== '') {
            $payload['password'] = Hash::make($data['password']);
        }

        if ($editing) {
            $user = User::findOrFail($this->editingUserId);
            $before = ['role' => $user->role->value, 'is_active' => $user->is_active];
            $user->update($payload);
            $this->syncScopes($user, $data['scopes_input']);

            Log::info('lodgely.user.updated', [
                'id' => $user->id,
                'actor' => auth()->id(),
                'before' => $before,
                'after' => ['role' => $user->role->value, 'is_active' => $user->is_active],
            ]);
            $this->dispatch('toast', message: __('User updated.'));
        } else {
            $user = User::create($payload);
            $this->syncScopes($user, $data['scopes_input']);

            Log::info('lodgely.user.created', [
                'id' => $user->id,
                'actor' => auth()->id(),
                'role' => $user->role->value,
            ]);
            $this->dispatch('toast', message: __('User created.'));
        }

        $this->close();
    }

    public function sendResetLink(int $id): void
    {
        $this->guardOperator();

        $user = User::findOrFail($id);
        if (! $user->is_active) {
            $this->dispatch('toast', message: __('Enable the account before issuing a reset link.'));

            return;
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        Log::info('lodgely.user.reset_link_sent', [
            'id' => $user->id,
            'actor' => auth()->id(),
            'status' => $status,
        ]);

        $this->dispatch('toast', message: __('Reset link emailed to :email.', ['email' => $user->email]));
    }

    public function toggleActive(int $id): void
    {
        $this->guardOperator();

        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            $this->dispatch('toast', message: __('You cannot deactivate yourself.'));

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);
        Log::info('lodgely.user.toggled', [
            'id' => $user->id,
            'actor' => auth()->id(),
            'is_active' => $user->is_active,
        ]);
    }

    // ---------------------------------------------------------------- render

    public function render(): View
    {
        $users = User::query()
            ->when($this->search, function ($q, $term) {
                $like = '%'.Like::escape(mb_strtolower($term)).'%';
                $q->where(function ($qq) use ($like) {
                    $qq->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$like])
                        ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '\\'", [$like]);
                });
            })
            ->when($this->roleFilter, fn ($q, $v) => $q->where('role', $v))
            ->withCount('leadScopes')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.users.users-page', [
            'users' => $users,
        ]);
    }

    // -------------------------------------------------------------- helpers

    private function guardOperator(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }

    private function syncScopes(User $user, string $rawInput): void
    {
        $user->leadScopes()->delete();

        if (! $user->isClient()) {
            return;
        }

        $scopes = collect(explode(',', $rawInput))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->unique()
            ->values();

        foreach ($scopes as $clientName) {
            UserLeadScope::create([
                'user_id' => $user->id,
                'client_name' => $clientName,
            ]);
        }
    }

    private function resetForm(): void
    {
        $this->editingUserId = null;
        $this->generatedPassword = null;
        $this->form = [
            'name' => '',
            'email' => '',
            'role' => 'operator',
            'client_type' => '',
            'password' => '',
            'is_active' => true,
            'scopes_input' => '',
        ];
        $this->resetErrorBag();
    }
}
