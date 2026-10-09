@props(['lead', 'plain' => false])
@php
    /** @var \App\Models\Lead $lead */
    $tags  = $lead->aiTags();
    $title = __('Set by AI · :reason', ['reason' => (string) $lead->ai_reason]);
    if ($tags !== []) {
        $title .= ' · '.implode(', ', $tags);
    }
@endphp
{{-- The "rated by AI" marker. Padding sits on the span itself (not a parent
     gap) so it never glues to the badge if the utility is missing from an
     older CSS bundle — see the Tailwind gotcha in CLAUDE.md. --}}
<span class="inline-flex items-center px-0.5 align-middle text-violet-500 dark:text-violet-400"
      @unless($plain) title="{{ $title }}" @endunless
      aria-label="{{ __('Priority set by AI') }}" data-ai-ranked="1">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-3 w-3" aria-hidden="true">
        <path d="M8 1l1.6 4.4L14 7l-4.4 1.6L8 13l-1.6-4.4L2 7l4.4-1.6z"/>
        <path d="M13 11l.6 1.4L15 13l-1.4.6L13 15l-.6-1.4L11 13l1.4-.6z" opacity=".7"/>
    </svg>
</span>
