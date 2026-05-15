<div class="space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">Email import <span class="text-xs font-normal text-slate-500 dark:text-slate-400">(mock)</span></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Generates simulated incoming leads as if they had been parsed from
            inbox emails. The real IMAP / parser backend is on the post-MVP roadmap;
            this page is here so the rest of the workflow can be tried end to end.
        </p>
    </div>

    <form wire:submit.prevent="runImport"
          class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 space-y-4 shadow-sm">
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">How many?</label>
                <input wire:model="count" type="number" min="1" max="50"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('count') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Default client</label>
                <input wire:model="defaultClient" type="text"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Default campaign</label>
                <input wire:model="defaultCampaign" type="text"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled"
                    class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors disabled:opacity-50 shadow-sm">
                <span wire:loading.remove>Pull mock emails</span>
                <span wire:loading>Pulling…</span>
            </button>
        </div>
    </form>

    @if($lastImport)
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-300">
            Pulled {{ $lastImport->rows_imported }} mock email lead(s),
            {{ $lastImport->rows_duplicate }} flagged as duplicates.
        </div>
    @endif

    <div>
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50 mb-2">Recent mock email imports</h2>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2 text-left">When</th>
                        <th class="px-3 py-2 text-left">Label</th>
                        <th class="px-3 py-2 text-right">Imported</th>
                        <th class="px-3 py-2 text-right">Duplicates</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($recentImports as $i)
                        <tr>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ $i->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 text-slate-800 dark:text-slate-200">{{ $i->label }}</td>
                            <td class="px-3 py-2 text-right dark:text-slate-300">{{ $i->rows_imported }}</td>
                            <td class="px-3 py-2 text-right dark:text-slate-300">{{ $i->rows_duplicate }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">No imports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
