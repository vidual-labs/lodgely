<div class="space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">Email import <span class="text-xs font-normal text-slate-500 dark:text-slate-400">(IMAP)</span></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Pulls unseen messages from your configured IMAP inbox, parses each email for
            contact-form fields, and ingests them as leads. Messages are marked as read
            after processing so they are never imported twice.
        </p>
    </div>

    @if(!$this->isConfigured())
        <div class="rounded-xl border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900 dark:text-amber-300">
            <strong>IMAP is not configured.</strong>
            Set <code class="font-mono text-xs bg-amber-100 dark:bg-amber-900/50 px-1 rounded">LODGELY_IMAP_HOST</code>,
            <code class="font-mono text-xs bg-amber-100 dark:bg-amber-900/50 px-1 rounded">LODGELY_IMAP_USERNAME</code>, and
            <code class="font-mono text-xs bg-amber-100 dark:bg-amber-900/50 px-1 rounded">LODGELY_IMAP_PASSWORD</code>
            in your <code class="font-mono text-xs bg-amber-100 dark:bg-amber-900/50 px-1 rounded">.env</code> file, then restart the app.
        </div>
    @else
        <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 px-4 py-3 text-xs text-slate-500 dark:text-slate-400 flex flex-wrap gap-4 shadow-sm">
            <span><span class="font-medium text-slate-700 dark:text-slate-300">Host</span> {{ $imapHost }}:{{ $imapPort }}</span>
            <span><span class="font-medium text-slate-700 dark:text-slate-300">Encryption</span> {{ strtoupper($imapEncryption) }}</span>
            <span><span class="font-medium text-slate-700 dark:text-slate-300">Mailbox</span> {{ $imapMailbox }}</span>
        </div>
    @endif

    <form wire:submit.prevent="runImport"
          class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 space-y-4 shadow-sm">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Default client <span class="font-normal text-slate-400 dark:text-slate-500">(optional)</span></label>
                <input wire:model="defaultClient" type="text"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('defaultClient') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Default campaign <span class="font-normal text-slate-400 dark:text-slate-500">(optional, falls back to email subject)</span></label>
                <input wire:model="defaultCampaign" type="text"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('defaultCampaign') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled"
                    @disabled(!$this->isConfigured())
                    class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                <span wire:loading.remove>Pull unseen emails</span>
                <span wire:loading>Pulling…</span>
            </button>
        </div>
    </form>

    @if($lastImport)
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-300">
            Pulled {{ $lastImport->rows_imported }} email lead(s),
            {{ $lastImport->rows_duplicate }} flagged as duplicates,
            {{ $lastImport->rows_invalid }} skipped (no contact info).
        </div>
    @endif

    <div>
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50 mb-2">Recent IMAP imports</h2>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2 text-left">When</th>
                        <th class="px-3 py-2 text-left">Label</th>
                        <th class="px-3 py-2 text-right">Imported</th>
                        <th class="px-3 py-2 text-right">Duplicates</th>
                        <th class="px-3 py-2 text-right">Skipped</th>
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
                        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">No IMAP imports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
