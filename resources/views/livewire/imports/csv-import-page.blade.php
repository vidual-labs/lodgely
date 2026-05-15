<div class="space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('CSV import') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Upload a UTF-8 CSV with a header row. lodgely recognizes common column names
            (<code class="text-xs dark:text-slate-300">name</code>, <code class="text-xs dark:text-slate-300">email</code>,
             <code class="text-xs dark:text-slate-300">phone</code>, <code class="text-xs dark:text-slate-300">message</code>,
             <code class="text-xs dark:text-slate-300">client</code>, <code class="text-xs dark:text-slate-300">campaign</code>).
            {{ __('Max :rows rows per file.', ['rows' => number_format(config('lodgely.importers.csv.max_rows'))]) }}
        </p>
    </div>

    <form wire:submit.prevent="submit"
          class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 space-y-4 shadow-sm">
        <div>
            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('CSV file') }}</label>
            <input wire:model="file" type="file" accept=".csv,text/csv,text/plain"
                   class="mt-1 block w-full text-sm text-slate-700 dark:text-slate-300">
            @error('file') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Default client') }} <span class="text-slate-400 dark:text-slate-500">{{ __('(optional)') }}</span></label>
                <input wire:model="defaultClient" type="text"
                       placeholder="{{ __('Used when row has no client column') }}"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Default campaign') }} <span class="text-slate-400 dark:text-slate-500">{{ __('(optional)') }}</span></label>
                <input wire:model="defaultCampaign" type="text"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors disabled:opacity-50 shadow-sm">
                <span wire:loading.remove>{{ __('Import') }}</span>
                <span wire:loading>{{ __('Importing…') }}</span>
            </button>
        </div>
    </form>

    @if($lastImport)
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-300">
            {{ __(':label — :imported imported, :duplicate duplicates, :invalid invalid (of :total rows).', [
                'label'     => $lastImport->label,
                'imported'  => $lastImport->rows_imported,
                'duplicate' => $lastImport->rows_duplicate,
                'invalid'   => $lastImport->rows_invalid,
                'total'     => $lastImport->rows_total,
            ]) }}
        </div>
    @endif

    <div>
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50 mb-2">{{ __('Recent CSV imports') }}</h2>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('When') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Label') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Imported') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Duplicates') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Invalid') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($recentImports as $i)
                        <tr>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ $i->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 text-slate-800 dark:text-slate-200">{{ $i->label }}</td>
                            <td class="px-3 py-2 text-right dark:text-slate-300">{{ $i->rows_imported }}</td>
                            <td class="px-3 py-2 text-right dark:text-slate-300">{{ $i->rows_duplicate }}</td>
                            <td class="px-3 py-2 text-right dark:text-slate-300">{{ $i->rows_invalid }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">{{ __('No CSV imports yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
