@props(['priorityOptions' => [], 'form' => []])

<div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
    <div role="dialog" aria-modal="true" aria-labelledby="manual-lead-title"
         class="w-full max-w-lg rounded-lg bg-white shadow-xl">
        <header class="border-b border-slate-200 px-5 py-3 flex justify-between items-center">
            <h2 id="manual-lead-title" class="text-base font-semibold text-slate-900">New lead</h2>
            <button type="button" wire:click="closeManualForm" aria-label="Close"
                    class="text-slate-400 hover:text-slate-700">✕</button>
        </header>

        <form wire:submit.prevent="saveManual" class="px-5 py-4 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-medium text-slate-600">Client</label>
                    <input wire:model="manual.client_name" type="text" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">Campaign</label>
                    <input wire:model="manual.campaign_name" type="text" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600">Full name</label>
                <input wire:model="manual.full_name" type="text" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                @error('manual.full_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-medium text-slate-600">Email</label>
                    <input wire:model="manual.email" type="email" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @error('manual.email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">Phone</label>
                    <input wire:model="manual.phone" type="text" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600">Message</label>
                <textarea wire:model="manual.message" rows="3" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600">Priority</label>
                <select wire:model="manual.priority" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach($priorityOptions as $o)
                        <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" wire:click="closeManualForm" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit"
                        wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-wait"
                        class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Save</button>
            </div>
        </form>
    </div>
</div>
