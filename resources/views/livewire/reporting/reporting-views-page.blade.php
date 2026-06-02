<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Report views') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Define custom reporting views and assign them to clients.') }}
            </p>
        </div>
        <button type="button" wire:click="openCreate"
                class="inline-flex items-center gap-2 rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
            + {{ __('New view') }}
        </button>
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                        <th class="px-3 py-2.5">{{ __('Name') }}</th>
                        <th class="px-3 py-2.5">{{ __('Columns') }}</th>
                        <th class="px-3 py-2.5">{{ __('Assigned to') }}</th>
                        <th class="px-3 py-2.5">{{ __('Status') }}</th>
                        <th class="px-3 py-2.5 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($views as $v)
                        <tr wire:key="view-{{ $v->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-3 py-2.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                                {{ $v->name }}
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($v->columnEnums() as $col)
                                        <span class="inline-flex items-center rounded-md bg-slate-100 dark:bg-slate-700/60 px-2 py-0.5 text-xs font-medium text-slate-700 dark:text-slate-300">
                                            {{ $col->label() }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-sm text-slate-600 dark:text-slate-400">
                                @if($v->assignedUsers->isEmpty())
                                    <span class="text-slate-400 dark:text-slate-500 italic">{{ __('No clients') }}</span>
                                @else
                                    <div class="flex flex-col gap-0.5">
                                        @foreach($v->assignedUsers as $u)
                                            <span>{{ $u->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if($v->is_live)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('Live') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-slate-700/60 px-2 py-0.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>{{ __('Hidden') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button" wire:click="toggleLive({{ $v->id }})"
                                            class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                                        {{ $v->is_live ? __('Hide') : __('Set live') }}
                                    </button>
                                    @if(config('lodgely.ai.enabled'))
                                        <button type="button" wire:click="generateAiSummary({{ $v->id }})"
                                                class="text-xs text-brand-600 dark:text-brand-400 hover:text-brand-800 dark:hover:text-brand-300 transition-colors">
                                            {{ __('Generate AI summary') }}
                                        </button>
                                    @endif
                                    <button type="button" wire:click="openEdit({{ $v->id }})"
                                            class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                                        {{ __('Edit') }}
                                    </button>
                                    <button type="button" wire:click="confirmDelete({{ $v->id }})"
                                            class="text-xs text-red-500 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition-colors">
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                {{ __('No report views yet.') }}
                                <button type="button" wire:click="openCreate" class="underline ml-1 hover:text-slate-700 dark:hover:text-slate-300">
                                    {{ __('Create the first one.') }}
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($views->hasPages())
            <div class="border-t border-slate-100 dark:border-slate-800 px-4 py-3">
                {{ $views->links() }}
            </div>
        @endif
    </div>

    {{-- Delete confirmation modal --}}
    @if($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-sm p-6">
                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-50">{{ __('Delete view?') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('This will permanently remove the view and revoke access for all assigned clients.') }}
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete"
                            class="rounded-lg px-3 py-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 border border-slate-200 dark:border-slate-700 transition-colors">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" wire:click="delete"
                            class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 transition-colors">
                        {{ __('Delete') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Create / edit form panel --}}
    @if($showForm)
        <div class="fixed inset-0 z-50 flex items-start justify-end p-4 bg-black/40 backdrop-blur-sm"
             x-data x-on:keydown.escape.window="$wire.close()">
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-lg mt-14 overflow-y-auto max-h-[calc(100vh-5rem)]">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-50">
                        {{ $editingId ? __('Edit view') : __('New view') }}
                    </h2>
                    <button type="button" wire:click="close"
                            class="text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="save" class="px-5 py-4 space-y-5">
                    {{-- Name --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('View name') }}
                        </label>
                        <input type="text" wire:model="form.name" maxlength="120"
                               placeholder="{{ __('e.g. Monthly performance') }}"
                               class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Columns --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Columns to show') }}
                            <span class="text-slate-400 font-normal ml-1">{{ __('(select at least one)') }}</span>
                        </label>
                        @error('form.columns')
                            <p class="mb-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <div class="space-y-1.5">
                            @foreach($allColumns as $col)
                                <label class="flex items-start gap-3 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2.5 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors
                                    {{ in_array($col->value, $form['columns']) ? 'border-brand-400 dark:border-brand-600 bg-brand-50/40 dark:bg-brand-900/10' : '' }}">
                                    <input type="checkbox" value="{{ $col->value }}"
                                           wire:model="form.columns"
                                           class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $col->label() }}</span>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $col->description() }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Client assignment --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Assign to clients') }}
                            <span class="text-slate-400 font-normal ml-1">{{ __('(optional)') }}</span>
                        </label>
                        @if($clientUsers->isEmpty())
                            <p class="text-sm text-slate-500 dark:text-slate-400 italic">
                                {{ __('No client users exist yet. Create one in Users first.') }}
                            </p>
                        @else
                            <div class="space-y-1.5">
                                @foreach($clientUsers as $u)
                                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors
                                        {{ in_array((string) $u->id, $form['user_ids']) ? 'border-brand-400 dark:border-brand-600 bg-brand-50/40 dark:bg-brand-900/10' : '' }}">
                                        <input type="checkbox" value="{{ $u->id }}"
                                               wire:model="form.user_ids"
                                               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                        <div>
                                            <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $u->name }}</span>
                                            <span class="ml-2 text-xs text-slate-500 dark:text-slate-400">{{ $u->email }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Visibility --}}
                    <div>
                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2.5 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors">
                            <input type="checkbox" wire:model="form.is_live"
                                   class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <div class="flex-1 min-w-0">
                                <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Live for clients') }}</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ __('When off, the view stays hidden from assigned clients until you set it live.') }}
                                </p>
                            </div>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-1 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" wire:click="close"
                                class="rounded-lg px-3 py-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 border border-slate-200 dark:border-slate-700 transition-colors">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit"
                                class="rounded-lg bg-slate-900 dark:bg-slate-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
                            {{ __('Save view') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
