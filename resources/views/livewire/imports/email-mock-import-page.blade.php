<div class="space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Email import <span class="text-xs font-normal text-slate-500">(mock)</span></h1>
        <p class="text-sm text-slate-500">
            Generates simulated incoming leads as if they had been parsed from
            inbox emails. The real IMAP / parser backend is on the post-MVP roadmap;
            this page is here so the rest of the workflow can be tried end to end.
        </p>
    </div>

    <form wire:submit.prevent="runImport" class="rounded-md border border-slate-200 bg-white p-5 space-y-4">
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="text-xs font-medium text-slate-600">How many?</label>
                <input wire:model="count" type="number" min="1" max="50"
                       class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                @error('count') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Default client</label>
                <input wire:model="defaultClient" type="text"
                       class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Default campaign</label>
                <input wire:model="defaultCampaign" type="text"
                       class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled"
                    class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50">
                <span wire:loading.remove>Pull mock emails</span>
                <span wire:loading>Pulling…</span>
            </button>
        </div>
    </form>

    @if($lastImport)
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            Pulled {{ $lastImport->rows_imported }} mock email lead(s),
            {{ $lastImport->rows_duplicate }} flagged as duplicates.
        </div>
    @endif

    <div>
        <h2 class="text-sm font-semibold text-slate-900 mb-2">Recent mock email imports</h2>
        <div class="rounded-md border border-slate-200 bg-white overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">When</th>
                        <th class="px-3 py-2 text-left">Label</th>
                        <th class="px-3 py-2 text-right">Imported</th>
                        <th class="px-3 py-2 text-right">Duplicates</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentImports as $i)
                        <tr>
                            <td class="px-3 py-2 text-slate-600">{{ $i->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 text-slate-800">{{ $i->label }}</td>
                            <td class="px-3 py-2 text-right">{{ $i->rows_imported }}</td>
                            <td class="px-3 py-2 text-right">{{ $i->rows_duplicate }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No imports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
