<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Report emails') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Compose modular report emails for clients — send now or schedule weekly / monthly.') }}
            </p>
        </div>
        <button type="button" wire:click="openCreate"
                class="inline-flex items-center gap-2 rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
            + {{ __('New report email') }}
        </button>
    </div>

    {{-- Templates table --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                        <th class="px-3 py-2.5">{{ __('Name') }}</th>
                        <th class="px-3 py-2.5">{{ __('Sections') }}</th>
                        <th class="px-3 py-2.5">{{ __('Recipients') }}</th>
                        <th class="px-3 py-2.5">{{ __('Schedule') }}</th>
                        <th class="px-3 py-2.5 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($emails as $email)
                        @php($sched = $email->schedules->first())
                        <tr wire:key="re-{{ $email->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-3 py-2.5 text-sm">
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ $email->name }}</div>
                                @if($email->reportingView)
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $email->reportingView->name }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex flex-wrap gap-1">
                                    @if($email->intro_markdown)
                                        <span class="inline-flex items-center rounded-md bg-slate-100 dark:bg-slate-700/60 px-2 py-0.5 text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('Intro') }}</span>
                                    @endif
                                    @if($email->include_kpi_strip)
                                        <span class="inline-flex items-center rounded-md bg-slate-100 dark:bg-slate-700/60 px-2 py-0.5 text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('KPI strip') }}</span>
                                    @endif
                                    @if($email->include_metrics_table)
                                        <span class="inline-flex items-center rounded-md bg-slate-100 dark:bg-slate-700/60 px-2 py-0.5 text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('Table') }}</span>
                                    @endif
                                    @if($email->include_ai_summary)
                                        <span class="inline-flex items-center rounded-md bg-indigo-100 dark:bg-indigo-900/40 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">{{ __('AI summary') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-sm text-slate-600 dark:text-slate-400">
                                @if($email->recipients->isEmpty())
                                    <span class="text-slate-400 dark:text-slate-500 italic">{{ __('No recipients') }}</span>
                                @else
                                    <div class="flex flex-col gap-0.5">
                                        @foreach($email->recipients->take(3) as $r)
                                            <span>{{ $r->name }}</span>
                                        @endforeach
                                        @if($email->recipients->count() > 3)
                                            <span class="text-xs text-slate-400">+{{ $email->recipients->count() - 3 }} {{ __('more') }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-sm">
                                @if($sched && $sched->is_active)
                                    <div class="text-slate-700 dark:text-slate-300">{{ $sched->cadence->label() }}</div>
                                    @if($sched->next_run_at)
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ __('Next') }}: {{ $sched->next_run_at->format('Y-m-d H:i') }} UTC
                                        </div>
                                    @endif
                                @else
                                    <span class="text-slate-400 dark:text-slate-500 italic text-xs">{{ __('Manual only') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button" wire:click="sendTest({{ $email->id }})"
                                            class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                                        {{ __('Send test') }}
                                    </button>
                                    <button type="button" wire:click="sendNow({{ $email->id }})"
                                            class="text-xs text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-300 transition-colors">
                                        {{ __('Send now') }}
                                    </button>
                                    <button type="button" wire:click="openEdit({{ $email->id }})"
                                            class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                                        {{ __('Edit') }}
                                    </button>
                                    <button type="button" wire:click="confirmDelete({{ $email->id }})"
                                            class="text-xs text-red-500 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition-colors">
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                {{ __('No report emails yet.') }}
                                <button type="button" wire:click="openCreate" class="underline ml-1 hover:text-slate-700 dark:hover:text-slate-300">
                                    {{ __('Create the first one.') }}
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent sends --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Recent sends') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                        <th class="px-3 py-2">{{ __('When') }}</th>
                        <th class="px-3 py-2">{{ __('Template') }}</th>
                        <th class="px-3 py-2">{{ __('Period') }}</th>
                        <th class="px-3 py-2">{{ __('Recipients') }}</th>
                        <th class="px-3 py-2">{{ __('Trigger') }}</th>
                        <th class="px-3 py-2">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($recentSends as $send)
                        <tr wire:key="send-{{ $send->id }}">
                            <td class="px-3 py-2 text-xs text-slate-600 dark:text-slate-400">
                                {{ $send->created_at?->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-3 py-2 text-sm text-slate-800 dark:text-slate-200">
                                {{ $send->email?->name ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">
                                {{ $send->period_from?->format('Y-m-d') }} → {{ $send->period_to?->format('Y-m-d') }}
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">
                                {{ count($send->recipient_user_ids ?? []) }}
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">
                                {{ $send->actor?->name ?? __('Schedule') }}
                            </td>
                            <td class="px-3 py-2 text-xs">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $send->status->badgeClasses() }}">
                                    {{ $send->status->label() }}
                                </span>
                                @if($send->error)
                                    <div class="mt-0.5 text-xs text-rose-600 dark:text-rose-400 max-w-xs truncate" title="{{ $send->error }}">{{ $send->error }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                                {{ __('No sends yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Delete confirmation modal --}}
    @if($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-sm p-6">
                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-50">{{ __('Delete report email?') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('This will remove the template, its schedule and its send history.') }}
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
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-xl mt-14 overflow-y-auto max-h-[calc(100vh-5rem)]">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-50">
                        {{ $editingId ? __('Edit report email') : __('New report email') }}
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
                            {{ __('Name') }}
                        </label>
                        <input type="text" wire:model="form.name" maxlength="120"
                               placeholder="{{ __('e.g. Acme monthly summary') }}"
                               class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>

                    {{-- Reporting view --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Reporting view') }}
                            <span class="text-slate-400 font-normal ml-1">{{ __('(metrics source — optional)') }}</span>
                        </label>
                        <select wire:model="form.client_reporting_view_id"
                                class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">{{ __('— No metrics, intro only —') }}</option>
                            @foreach($views as $v)
                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Intro markdown --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Intro note') }}
                            <span class="text-slate-400 font-normal ml-1">{{ __('(markdown supported)') }}</span>
                        </label>
                        <textarea wire:model="form.intro_markdown" rows="4" maxlength="10000"
                                  placeholder="{{ __('Hi {name}, here is the latest snapshot of your campaign performance…') }}"
                                  class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                    </div>

                    {{-- Sections --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Sections to include') }}
                        </label>
                        <div class="space-y-1.5">
                            <label class="flex items-start gap-3 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors">
                                <input type="checkbox" wire:model="form.include_kpi_strip"
                                       class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <div>
                                    <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('KPI summary strip') }}</div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Top-line totals for each column in the selected view.') }}</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors">
                                <input type="checkbox" wire:model="form.include_metrics_table"
                                       class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <div>
                                    <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Monthly metrics table') }}</div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Per-month rows × selected columns.') }}</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors">
                                <input type="checkbox" wire:model="form.include_ai_summary"
                                       class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <div>
                                    <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('AI summary') }}</div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Includes the latest operator-approved AI summary for this view. If none exists at send time, the section is silently omitted.') }}</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Period months + subject --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                {{ __('Period (months)') }}
                            </label>
                            <input type="number" min="1" max="24" wire:model="form.period_months"
                                   class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                            @error('form.period_months')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                {{ __('Subject') }}
                            </label>
                            <input type="text" wire:model="form.subject_template" maxlength="200"
                                   class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <p class="mt-1 text-xs text-slate-400">{{ __('Tokens:') }} @{{period}}, @{{client}}, @{{name}}</p>
                        </div>
                    </div>

                    {{-- Recipients --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Recipients (client users)') }}
                        </label>
                        @if($clientUsers->isEmpty())
                            <p class="text-sm text-slate-500 dark:text-slate-400 italic">
                                {{ __('No active client users exist yet.') }}
                            </p>
                        @else
                            <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                @foreach($clientUsers as $u)
                                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors
                                        {{ in_array((string) $u->id, $form['recipient_ids']) ? 'border-brand-400 dark:border-brand-600 bg-brand-50/40 dark:bg-brand-900/10' : '' }}">
                                        <input type="checkbox" value="{{ $u->id }}"
                                               wire:model="form.recipient_ids"
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

                    {{-- Schedule sub-form --}}
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 space-y-3">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model.live="form.schedule.is_active"
                                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Enable schedule') }}</span>
                        </label>

                        @if($form['schedule']['is_active'])
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Cadence') }}</label>
                                    <select wire:model.live="form.schedule.cadence"
                                            class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                                        @foreach($cadences as $c)
                                            <option value="{{ $c->value }}">{{ $c->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Timezone') }}</label>
                                    <input type="text" wire:model="form.schedule.timezone" maxlength="64"
                                           class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                                </div>

                                @if($form['schedule']['cadence'] === 'weekly')
                                    <div>
                                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Day of week') }}</label>
                                        <select wire:model="form.schedule.day_of_week"
                                                class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                                            <option value="0">{{ __('Sunday') }}</option>
                                            <option value="1">{{ __('Monday') }}</option>
                                            <option value="2">{{ __('Tuesday') }}</option>
                                            <option value="3">{{ __('Wednesday') }}</option>
                                            <option value="4">{{ __('Thursday') }}</option>
                                            <option value="5">{{ __('Friday') }}</option>
                                            <option value="6">{{ __('Saturday') }}</option>
                                        </select>
                                    </div>
                                @endif

                                @if($form['schedule']['cadence'] === 'monthly')
                                    <div>
                                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Day of month (1–28)') }}</label>
                                        <input type="number" min="1" max="28" wire:model="form.schedule.day_of_month"
                                               class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                @endif

                                @if($form['schedule']['cadence'] !== 'one_off')
                                    <div>
                                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Hour (0–23)') }}</label>
                                        <input type="number" min="0" max="23" wire:model="form.schedule.hour"
                                               class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                @endif

                                @if($form['schedule']['cadence'] === 'one_off')
                                    <div class="col-span-2">
                                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Send at') }}</label>
                                        <input type="datetime-local" wire:model="form.schedule.send_at"
                                               class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                                        <p class="mt-1 text-xs text-slate-400">{{ __('Interpreted in the timezone above.') }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Active toggle --}}
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="form.is_active"
                               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('Template is active') }}</span>
                    </label>

                    <div class="flex justify-end gap-2 pt-1 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" wire:click="close"
                                class="rounded-lg px-3 py-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 border border-slate-200 dark:border-slate-700 transition-colors">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit"
                                class="rounded-lg bg-slate-900 dark:bg-slate-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
                            {{ __('Save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
