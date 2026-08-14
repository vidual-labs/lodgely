@props(['lead', 'option', 'current' => false, 'auto' => false])

{{-- One status pill. Rendered twice on the lead panel — in the always-visible
     row and inside the collapsed "Intake" disclosure — so the markup (and the
     setStatus() call the panel tests assert on) lives in one place.
     `statusNudge` comes from the panel's root x-data. `auto` flags the two
     statuses {@see \App\Domain\Leads\Services\LeadStatusAutomation} sets on
     its own (Reviewed, Pending) so they read as "set for you", not "yours to
     click". --}}
<button type="button"
        wire:click="setStatus({{ $lead->id }}, '{{ $option['value'] }}')"
        aria-pressed="{{ $current ? 'true' : 'false' }}"
        x-bind:class="{ 'ring-2 ring-offset-1 dark:ring-offset-slate-900 animate-pulse': statusNudge === @js($option['value']) }"
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs ring-1 ring-inset transition-colors {{ $current ? $option['badge'].' font-semibold' : 'bg-white dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 ring-slate-300/60 dark:ring-slate-600/40 hover:text-slate-800 dark:hover:text-slate-200' }}"
        title="{{ ($current ? $option['label'] : __('Set status to :label', ['label' => $option['label']])) . ($auto ? ' — '.__('set automatically') : '') }}">
    @if($current)<span aria-hidden="true">✓</span>@endif
    <span>{{ $option['label'] }}</span>
    @if($auto)<span class="text-[10px] opacity-70">· {{ __('automatic') }}</span>@endif
</button>
