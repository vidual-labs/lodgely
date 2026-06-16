<div class="space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Backup & recovery') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ __('Create a full database backup, download it to a local machine, or restore from a previously downloaded archive. Useful for migrating to a new server or recovering from a bad state.') }}
        </p>
    </div>

    @if($notice)
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-300">
            {{ $notice }}
        </div>
    @endif

    @if($error)
        <div class="rounded-xl border border-rose-200 dark:border-rose-800/50 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-sm text-rose-900 dark:text-rose-300">
            {{ $error }}
        </div>
    @endif

    {{-- Create + list --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Backups') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Each backup is a single .zip archive containing a full database dump. Download it somewhere safe — it is not copied off this server automatically.') }}
                </p>
            </div>
            <form method="POST" action="{{ route('settings.backups.create') }}" class="shrink-0"
                  x-data="{ submitting: false }" x-on:submit="submitting = true">
                @csrf
                <button type="submit" x-bind:disabled="submitting"
                        class="rounded-lg bg-slate-900 dark:bg-slate-100 px-4 py-2 text-sm font-medium text-white dark:text-slate-900 hover:opacity-90 transition-opacity disabled:opacity-60">
                    <span x-show="!submitting">{{ __('Create backup now') }}</span>
                    <span x-show="submitting" x-cloak>{{ __('Dumping database…') }}</span>
                </button>
            </form>
        </div>

        <div class="rounded-xl border border-slate-100 dark:border-slate-800 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('File') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Created') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Size') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($backups as $b)
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs text-slate-700 dark:text-slate-300">{{ $b['filename'] }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ \Illuminate\Support\Carbon::parse($b['created_at'])->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ $b['size_human'] }}</td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('settings.backups.download', $b['filename']) }}"
                                       class="rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                        {{ __('Download') }}
                                    </a>
                                    <form method="POST" action="{{ route('settings.backups.delete') }}"
                                          x-on:submit="if (! confirm(@js(__('Delete :name? This cannot be undone.', ['name' => $b['filename']])))) $event.preventDefault()">
                                        @csrf
                                        <input type="hidden" name="filename" value="{{ $b['filename'] }}">
                                        <button type="submit"
                                                class="rounded-lg border border-rose-300 dark:border-rose-700 px-2.5 py-1 text-xs font-medium text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors">
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                                {{ __('No backups yet — create one above.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Restore --}}
    <div class="rounded-xl border border-rose-200 dark:border-rose-900/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
        <div>
            <h2 class="text-sm font-semibold text-rose-700 dark:text-rose-300">{{ __('Restore from a backup') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Uploading a backup here replaces every table in the database with the contents of the archive — leads, users, settings, everything. Current data that is not in the archive is gone for good. You will be signed out when it finishes. Take a fresh backup first.') }}
            </p>
        </div>

        {{-- Native multipart form, NOT Livewire. The Livewire file-upload --}}
        {{-- (wire:model) + wire:submit path hung on "Uploading…" and --}}
        {{-- silently dropped the submit in production — see BackupController --}}
        {{-- and the morph-drop notes in CLAUDE.md. --}}
        <form method="POST" action="{{ route('settings.backups.restore') }}" enctype="multipart/form-data" class="space-y-3"
              x-data="{ submitting: false }"
              x-on:submit="if (! confirm(@js(__('This will permanently overwrite the current database and sign you out. Are you absolutely sure?')))) { $event.preventDefault(); return; } submitting = true">
            @csrf
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Backup archive (.zip)') }}</label>
                <input name="restore_file" type="file" accept=".zip,application/zip" required
                       class="mt-1 block w-full text-sm text-slate-700 dark:text-slate-300">
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">
                    {{ __('Type :word to confirm you understand this replaces all current data', ['word' => 'RESTORE']) }}
                </label>
                <input name="restore_confirmation" type="text" placeholder="RESTORE" required
                       class="mt-1 block w-48 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm font-mono focus:border-rose-500 focus:ring-rose-500">
            </div>

            <div class="flex justify-end">
                <button type="submit" x-bind:disabled="submitting"
                        class="rounded-lg border border-rose-300 dark:border-rose-700 bg-rose-50 dark:bg-rose-950/40 px-4 py-2 text-sm font-medium text-rose-700 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-950/60 transition-colors disabled:opacity-60">
                    <span x-show="!submitting">{{ __('Restore and overwrite database') }}</span>
                    <span x-show="submitting" x-cloak>{{ __('Restoring… do not close this tab') }}</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Command line alternative --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-2 text-sm">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Prefer the command line?') }}</h2>
        <p class="text-slate-600 dark:text-slate-400">{{ __('The same operations are available as artisan commands — handy for cron jobs or scripted migrations:') }}</p>
        <pre class="rounded-lg bg-slate-50 dark:bg-slate-800/60 px-3 py-2 text-xs font-mono text-slate-700 dark:text-slate-300 overflow-x-auto">docker compose exec app php artisan lodgely:backup:create --keep=7
docker compose exec app php artisan lodgely:backup:restore /app/storage/app/private/backups/lodgely-backup-20260101-120000.zip</pre>
    </div>
</div>
