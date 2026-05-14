<div class="space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">CSV import</h1>
        <p class="text-sm text-slate-500">
            Upload a UTF-8 CSV with a header row. lodgely recognizes common column names
            (<code class="text-xs">name</code>, <code class="text-xs">email</code>,
             <code class="text-xs">phone</code>, <code class="text-xs">message</code>,
             <code class="text-xs">client</code>, <code class="text-xs">campaign</code>).
            Max {{ number_format(config('lodgely.importers.csv.max_rows')) }} rows per file.
        </p>
    </div>

    <form wire:submit.prevent="submit" class="rounded-md border border-slate-200 bg-white p-5 space-y-4">
        <div>
            <label class="text-xs font-medium text-slate-600">CSV file</label>
            <input wire:model="file" type="file" accept=".csv,text/csv,text/plain"
                   class="mt-1 block w-full text-sm">
            @error('file') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-xs font-medium text-slate-600">Default client (optional)</label>
                <input wire:model="defaultClient" type="text"
                       placeholder="Used when row has no client column"
                       class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Default campaign (optional)</label>
                <input wire:model="defaultCampaign" type="text"
                       class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50">
                <span wire:loading.remove>Import</span>
                <span wire:loading>Importing…</span>
            </button>
        </div>
    </form>

    @if($lastImport)
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <strong>{{ $lastImport->label }}</strong> —
            {{ $lastImport->rows_imported }} imported,
            {{ $lastImport->rows_duplicate }} duplicates,
            {{ $lastImport->rows_invalid }} invalid
            (of {{ $lastImport->rows_total }} rows).
        </div>
    @endif

    <div>
        <h2 class="text-sm font-semibold text-slate-900 mb-2">Recent CSV imports</h2>
        <div class="rounded-md border border-slate-200 bg-white overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">When</th>
                        <th class="px-3 py-2 text-left">Label</th>
                        <th class="px-3 py-2 text-right">Imported</th>
                        <th class="px-3 py-2 text-right">Duplicates</th>
                        <th class="px-3 py-2 text-right">Invalid</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentImports as $i)
                        <tr>
                            <td class="px-3 py-2 text-slate-600">{{ $i->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 text-slate-800">{{ $i->label }}</td>
                            <td class="px-3 py-2 text-right">{{ $i->rows_imported }}</td>
                            <td class="px-3 py-2 text-right">{{ $i->rows_duplicate }}</td>
                            <td class="px-3 py-2 text-right">{{ $i->rows_invalid }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No CSV imports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
