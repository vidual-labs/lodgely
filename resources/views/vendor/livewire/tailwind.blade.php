@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">

        {{-- mobile: prev / next only --}}
        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-slate-400 dark:text-slate-600 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 cursor-default leading-5 rounded-lg">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <button type="button" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled"
                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 leading-5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    {!! __('pagination.previous') !!}
                </button>
            @endif

            @if ($paginator->hasMorePages())
                <button type="button" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled"
                        class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 leading-5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    {!! __('pagination.next') !!}
                </button>
            @else
                <span class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-slate-400 dark:text-slate-600 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 cursor-default leading-5 rounded-lg">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        {{-- desktop: summary + page numbers --}}
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-5">
                    {!! __('Showing') !!}
                    @if ($paginator->firstItem())
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $paginator->firstItem() }}</span>
                        {!! __('to') !!}
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $paginator->lastItem() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                    {!! __('of') !!}
                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ $paginator->total() }}</span>
                    {!! __('results') !!}
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex rounded-lg shadow-sm overflow-hidden border border-slate-200 dark:border-slate-700">
                    {{-- Previous --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="relative inline-flex items-center px-2 py-1 text-xs text-slate-300 dark:text-slate-600 bg-white dark:bg-slate-900 cursor-default leading-5 border-r border-slate-200 dark:border-slate-700" aria-hidden="true">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </span>
                        </span>
                    @else
                        <button type="button" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" rel="prev"
                                class="relative inline-flex items-center px-2 py-1 text-xs text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900 leading-5 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-800 dark:hover:text-slate-100 transition-colors border-r border-slate-200 dark:border-slate-700" aria-label="{{ __('pagination.previous') }}">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </button>
                    @endif

                    {{-- Page links --}}
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center px-2.5 py-1 -ml-px text-xs text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-900 cursor-default leading-5 border-r border-slate-200 dark:border-slate-700">{{ $element }}</span>
                            </span>
                        @endif
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center px-2.5 py-1 -ml-px text-xs font-semibold text-white bg-slate-900 dark:bg-brand-600 cursor-default leading-5 border-r border-slate-900 dark:border-brand-600">{{ $page }}</span>
                                    </span>
                                @else
                                    <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled"
                                            class="relative inline-flex items-center px-2.5 py-1 -ml-px text-xs text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900 leading-5 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-800 dark:hover:text-slate-100 transition-colors border-r border-slate-200 dark:border-slate-700" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</button>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next --}}
                    @if ($paginator->hasMorePages())
                        <button type="button" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" rel="next"
                                class="relative inline-flex items-center px-2 py-1 -ml-px text-xs text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900 leading-5 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-800 dark:hover:text-slate-100 transition-colors" aria-label="{{ __('pagination.next') }}">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                        </button>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="relative inline-flex items-center px-2 py-1 -ml-px text-xs text-slate-300 dark:text-slate-600 bg-white dark:bg-slate-900 cursor-default leading-5" aria-hidden="true">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
