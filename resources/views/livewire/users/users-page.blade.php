<div class="space-y-6">
    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">Users</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Operators see every lead in this workspace. Clients are scoped to one or more
                <span class="font-medium">client names</span> and only see their own leads.
            </p>
        </div>
        <button type="button" wire:click="openCreate"
                class="inline-flex items-center gap-2 rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
            + New user
        </button>
    </div>

    {{-- filters --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-3 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-8">
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="Search name or email…"
                       class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="md:col-span-4">
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Role</label>
                <select wire:model.live="roleFilter"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All</option>
                    <option value="operator">Operator</option>
                    <option value="client">Client</option>
                </select>
            </div>
        </div>
    </div>

    {{-- table --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400">
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Email</th>
                        <th class="px-3 py-2 w-[110px]">Role</th>
                        <th class="px-3 py-2 w-[120px]">Scopes</th>
                        <th class="px-3 py-2 w-[100px]">Status</th>
                        <th class="px-3 py-2 w-[140px] text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($users as $u)
                        <tr wire:key="user-{{ $u->id }}" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-3 py-2 text-sm">
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ $u->name }}
                                    @if($u->id === auth()->id())
                                        <span class="ml-1 text-[10px] uppercase tracking-wider text-slate-400 dark:text-slate-500">you</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">{{ $u->email }}</td>
                            <td class="px-3 py-2">
                                @if($u->isOperator())
                                    <span class="inline-flex items-center rounded-md bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-400 ring-1 ring-inset ring-indigo-600/20 dark:ring-indigo-500/30">Operator</span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-xs font-medium text-slate-700 dark:text-slate-300 ring-1 ring-inset ring-slate-500/20 dark:ring-slate-500/30">Client</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">
                                @if($u->isOperator())
                                    <span class="text-slate-400 dark:text-slate-500">all</span>
                                @else
                                    {{ $u->lead_scopes_count }}
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if($u->is_active)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:text-emerald-400 ring-1 ring-inset ring-emerald-600/20 dark:ring-emerald-500/30">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-400 ring-1 ring-inset ring-slate-400/30">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Disabled
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right text-sm">
                                <button wire:click="openEdit({{ $u->id }})"
                                        class="text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">Edit</button>
                                @if($u->id !== auth()->id())
                                    <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>
                                    <button wire:click="toggleActive({{ $u->id }})"
                                            class="transition-colors {{ $u->is_active ? 'text-slate-500 dark:text-slate-400 hover:text-rose-700 dark:hover:text-rose-400' : 'text-emerald-700 dark:text-emerald-500 hover:text-emerald-800 dark:hover:text-emerald-400' }}">
                                        {{ $u->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No users match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 dark:border-slate-700/50 px-3 py-2">
            {{ $users->links() }}
        </div>
    </div>

    {{-- create / edit modal --}}
    @if($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 dark:bg-black/60 px-4">
            <div role="dialog" aria-modal="true" aria-labelledby="users-dialog-title"
                 class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 shadow-2xl dark:shadow-black/50 border border-slate-200 dark:border-slate-700/50">
                <header class="border-b border-slate-200 dark:border-slate-700/50 px-5 py-3 flex justify-between items-center">
                    <h2 id="users-dialog-title" class="text-base font-semibold text-slate-900 dark:text-slate-50">
                        {{ $editingUserId ? 'Edit user' : 'New user' }}
                    </h2>
                    <button type="button" wire:click="close" aria-label="Close"
                            class="text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">✕</button>
                </header>

                <form wire:submit.prevent="save" class="px-5 py-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                            <input wire:model="form.name" type="text"
                                   class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                            @error('form.name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Email</label>
                            <input wire:model="form.email" type="email"
                                   class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                            @error('form.email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Role</label>
                            <select wire:model.live="form.role"
                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="operator">Operator</option>
                                <option value="client">Client</option>
                            </select>
                            @error('form.role') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input type="checkbox" wire:model="form.is_active"
                                       class="rounded border-slate-300 dark:border-slate-600 text-brand-500 focus:ring-brand-500">
                                Active
                            </label>
                            @error('form.is_active') <p class="ml-3 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if($form['role'] === 'client')
                        <div>
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">
                                Client name scopes
                                <span class="text-slate-400 dark:text-slate-500">(comma-separated)</span>
                            </label>
                            <input wire:model="form.scopes_input" type="text"
                                   placeholder="e.g. Northwind Studio, Acme Wellness"
                                   class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                This user will only see leads whose <code class="text-[11px] dark:text-slate-300">client_name</code> matches one of the entries above (case-insensitive).
                            </p>
                            @error('form.scopes_input') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">
                                Password
                                @if($editingUserId)
                                    <span class="text-slate-400 dark:text-slate-500">(leave empty to keep current)</span>
                                @endif
                            </label>
                            <button type="button" wire:click="generatePassword"
                                    class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">Generate</button>
                        </div>
                        <input wire:model="form.password" type="text" autocomplete="off"
                               class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-mono focus:border-brand-500 focus:ring-brand-500">
                        @error('form.password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        @if($generatedPassword)
                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-500">
                                Share this password securely — it is shown <strong>only once</strong> and is not retrievable later.
                            </p>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="close"
                                class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-wait"
                                class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors">
                            {{ $editingUserId ? 'Save changes' : 'Create user' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
