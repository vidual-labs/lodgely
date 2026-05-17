@php
    $isOperator = auth()->check() && auth()->user()->isOperator();
    $aiEnabled  = (bool) config('lodgely.ai.enabled');
    $imapEnabled = (bool) config('lodgely.importers.email.imap.host');

    // Group memberships so we can highlight a dropdown when any of its children is active
    $importRoutes    = ['imports.csv', 'imports.email', 'imports.email-imap'];
    $reportingRoutes = ['reporting', 'reporting.views', 'reporting.emails'];
    $aiRoutes        = ['ai.drafts', 'settings.ai'];

    $importsActive   = request()->routeIs(...$importRoutes);
    $reportingActive = request()->routeIs(...$reportingRoutes);
    $aiActive        = request()->routeIs(...$aiRoutes);

    $itemBase   = 'px-3 py-1.5 rounded-lg transition-colors hover:bg-slate-100 dark:hover:bg-slate-800';
    $itemActive = 'text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-800 font-medium';
    $itemIdle   = 'text-slate-600 dark:text-slate-400';

    $menuItem        = 'block px-3 py-2 text-sm rounded-md transition-colors hover:bg-slate-100 dark:hover:bg-slate-800';
    $menuItemActive  = 'text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-800 font-medium';
    $menuItemIdle    = 'text-slate-700 dark:text-slate-300';
@endphp

<header class="sticky top-0 z-30 border-b border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm"
        x-data="{ mobileOpen: false }">
    <div class="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
        <div class="flex items-center gap-6 min-w-0">
            <a href="{{ route('inbox') }}" class="flex items-center group shrink-0" aria-label="{{ config('lodgely.brand.name') }}">
                <img src="{{ asset('img/logo.png') }}"
                     alt="{{ config('lodgely.brand.name') }}"
                     class="h-8 w-auto rounded-md shadow-sm group-hover:shadow-md transition-shadow">
            </a>

            {{-- Desktop nav --}}
            <nav class="hidden lg:flex items-center gap-0.5 text-sm">
                <a href="{{ route('inbox') }}"
                   class="{{ $itemBase }} {{ request()->routeIs('inbox') ? $itemActive : $itemIdle }}">
                    {{ __('Inbox') }}
                </a>

                @auth
                    @if($isOperator)
                        {{-- Imports dropdown --}}
                        <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                            <button type="button"
                                    @click="open = !open"
                                    @click.outside="open = false"
                                    :aria-expanded="open"
                                    aria-haspopup="true"
                                    class="{{ $itemBase }} {{ $importsActive ? $itemActive : $itemIdle }} inline-flex items-center gap-1">
                                {{ __('Imports') }}
                                <svg class="h-3 w-3 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4.5 6 7.5 9 4.5"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.100ms
                                 class="absolute left-0 mt-1 w-56 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg dark:shadow-black/40 p-1"
                                 role="menu">
                                <a href="{{ route('imports.csv') }}" role="menuitem"
                                   class="{{ $menuItem }} {{ request()->routeIs('imports.csv') ? $menuItemActive : $menuItemIdle }}">
                                    {{ __('CSV import') }}
                                </a>
                                <a href="{{ route('imports.email') }}" role="menuitem"
                                   class="{{ $menuItem }} {{ request()->routeIs('imports.email') ? $menuItemActive : $menuItemIdle }}">
                                    {{ __('Email (mock)') }}
                                </a>
                                @if($imapEnabled)
                                    <a href="{{ route('imports.email-imap') }}" role="menuitem"
                                       class="{{ $menuItem }} {{ request()->routeIs('imports.email-imap') ? $menuItemActive : $menuItemIdle }}">
                                        {{ __('Email (IMAP)') }}
                                    </a>
                                @endif
                            </div>
                        </div>

                        {{-- Reporting dropdown --}}
                        <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                            <button type="button"
                                    @click="open = !open"
                                    @click.outside="open = false"
                                    :aria-expanded="open"
                                    aria-haspopup="true"
                                    class="{{ $itemBase }} {{ $reportingActive ? $itemActive : $itemIdle }} inline-flex items-center gap-1">
                                {{ __('Reporting') }}
                                <svg class="h-3 w-3 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4.5 6 7.5 9 4.5"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.100ms
                                 class="absolute left-0 mt-1 w-56 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg dark:shadow-black/40 p-1"
                                 role="menu">
                                <a href="{{ route('reporting') }}" role="menuitem"
                                   class="{{ $menuItem }} {{ request()->routeIs('reporting') ? $menuItemActive : $menuItemIdle }}">
                                    {{ __('Overview') }}
                                </a>
                                <a href="{{ route('reporting.views') }}" role="menuitem"
                                   class="{{ $menuItem }} {{ request()->routeIs('reporting.views') ? $menuItemActive : $menuItemIdle }}">
                                    {{ __('Report views') }}
                                </a>
                                <a href="{{ route('reporting.emails') }}" role="menuitem"
                                   class="{{ $menuItem }} {{ request()->routeIs('reporting.emails') ? $menuItemActive : $menuItemIdle }}">
                                    {{ __('Report emails') }}
                                </a>
                            </div>
                        </div>

                        <a href="{{ route('users') }}"
                           class="{{ $itemBase }} {{ request()->routeIs('users') ? $itemActive : $itemIdle }}">
                            {{ __('Users') }}
                        </a>
                        <a href="{{ route('webhooks') }}"
                           class="{{ $itemBase }} {{ request()->routeIs('webhooks') ? $itemActive : $itemIdle }}">
                            {{ __('Webhooks') }}
                        </a>

                        @if($aiEnabled)
                            {{-- AI dropdown --}}
                            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                                <button type="button"
                                        @click="open = !open"
                                        @click.outside="open = false"
                                        :aria-expanded="open"
                                        aria-haspopup="true"
                                        class="{{ $itemBase }} {{ $aiActive ? $itemActive : $itemIdle }} inline-flex items-center gap-1">
                                    {{ __('AI') }}
                                    <svg class="h-3 w-3 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4.5 6 7.5 9 4.5"/></svg>
                                </button>
                                <div x-show="open" x-cloak x-transition.opacity.duration.100ms
                                     class="absolute left-0 mt-1 w-48 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg dark:shadow-black/40 p-1"
                                     role="menu">
                                    <a href="{{ route('ai.drafts') }}" role="menuitem"
                                       class="{{ $menuItem }} {{ request()->routeIs('ai.drafts') ? $menuItemActive : $menuItemIdle }}">
                                        {{ __('AI drafts') }}
                                    </a>
                                    <a href="{{ route('settings.ai') }}" role="menuitem"
                                       class="{{ $menuItem }} {{ request()->routeIs('settings.ai') ? $menuItemActive : $menuItemIdle }}">
                                        {{ __('AI settings') }}
                                    </a>
                                </div>
                            </div>
                        @endif
                    @else
                        <a href="{{ route('my-reports') }}"
                           class="{{ $itemBase }} {{ request()->routeIs('my-reports') ? $itemActive : $itemIdle }}">
                            {{ __('My reports') }}
                        </a>
                    @endif
                @endauth
            </nav>
        </div>

        <div class="flex items-center gap-2">
            {{-- Mobile hamburger trigger (auth only — guests have nothing to navigate to) --}}
            @auth
                <button type="button"
                        @click="mobileOpen = !mobileOpen"
                        :aria-expanded="mobileOpen"
                        aria-controls="mobile-nav-panel"
                        aria-label="{{ __('Toggle navigation') }}"
                        class="lg:hidden inline-flex items-center justify-center h-9 w-9 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <svg x-show="!mobileOpen" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg x-show="mobileOpen" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M6 18L18 6"/></svg>
                </button>
            @endauth

            {{-- Dark / Light mode pill switch --}}
            <div x-data="{
                    dark: document.documentElement.classList.contains('dark'),
                    saveTheme(theme) {
                        localStorage.setItem('theme', theme);
                        @auth
                        fetch('{{ route('user.theme') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: JSON.stringify({ theme })
                        });
                        @endauth
                    }
                 }"
                 class="hidden sm:flex items-center rounded-lg bg-slate-100 dark:bg-slate-800/80 p-0.5 text-xs font-medium">
                <button @click="dark = false; document.documentElement.classList.remove('dark'); saveTheme('light')"
                        :class="!dark ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 dark:text-slate-400'"
                        class="flex items-center gap-1 px-2.5 py-1 rounded-md transition-all"
                        :aria-pressed="!dark ? 'true' : 'false'"
                        aria-label="{{ __('Switch to light mode') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                        <circle cx="12" cy="12" r="5"/>
                        <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                        <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                    <span class="hidden md:inline">{{ __('Light') }}</span>
                </button>
                <button @click="dark = true; document.documentElement.classList.add('dark'); saveTheme('dark')"
                        :class="dark ? 'bg-slate-700 shadow-sm text-slate-100' : 'text-slate-500 dark:text-slate-400'"
                        class="flex items-center gap-1 px-2.5 py-1 rounded-md transition-all"
                        :aria-pressed="dark ? 'true' : 'false'"
                        aria-label="{{ __('Switch to dark mode') }}">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <span class="hidden md:inline">{{ __('Dark') }}</span>
                </button>
            </div>

            {{-- Language switcher --}}
            <div class="hidden sm:flex items-center rounded-lg bg-slate-100 dark:bg-slate-800/80 p-0.5 text-xs font-medium">
                <form method="POST" action="{{ route('locale') }}">
                    @csrf
                    <input type="hidden" name="locale" value="en">
                    <button type="submit"
                            class="{{ app()->getLocale() === 'en' ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400' }} px-2.5 py-1 rounded-md transition-all">
                        EN
                    </button>
                </form>
                <form method="POST" action="{{ route('locale') }}">
                    @csrf
                    <input type="hidden" name="locale" value="de">
                    <button type="submit"
                            class="{{ app()->getLocale() === 'de' ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400' }} px-2.5 py-1 rounded-md transition-all">
                        DE
                    </button>
                </form>
            </div>

            @auth
                <div class="flex items-center gap-3 text-sm">
                    <span class="hidden md:inline-flex items-center gap-2 text-slate-600 dark:text-slate-400">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold">
                            {{ auth()->user()->initials }}
                        </span>
                        <span class="truncate max-w-[180px]">
                            {{ auth()->user()->name }}
                            <span class="text-xs text-slate-400 dark:text-slate-600">· {{ auth()->user()->role->label() }}</span>
                        </span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="hidden sm:inline text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors text-sm">{{ __('Sign out') }}</button>
                    </form>
                </div>
            @endauth
        </div>
    </div>

    {{-- Mobile nav panel --}}
    @auth
        <div x-show="mobileOpen" x-cloak
             x-transition.opacity.duration.150ms
             id="mobile-nav-panel"
             class="lg:hidden border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
            <nav class="mx-auto max-w-[1400px] px-4 sm:px-6 py-3 space-y-1 text-sm">
                <a href="{{ route('inbox') }}"
                   class="{{ $menuItem }} {{ request()->routeIs('inbox') ? $menuItemActive : $menuItemIdle }}">
                    {{ __('Inbox') }}
                </a>

                @if($isOperator)
                    <div class="pt-2 pb-1 px-3 text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-semibold">{{ __('Imports') }}</div>
                    <a href="{{ route('imports.csv') }}"
                       class="{{ $menuItem }} {{ request()->routeIs('imports.csv') ? $menuItemActive : $menuItemIdle }}">{{ __('CSV import') }}</a>
                    <a href="{{ route('imports.email') }}"
                       class="{{ $menuItem }} {{ request()->routeIs('imports.email') ? $menuItemActive : $menuItemIdle }}">{{ __('Email (mock)') }}</a>
                    @if($imapEnabled)
                        <a href="{{ route('imports.email-imap') }}"
                           class="{{ $menuItem }} {{ request()->routeIs('imports.email-imap') ? $menuItemActive : $menuItemIdle }}">{{ __('Email (IMAP)') }}</a>
                    @endif

                    <div class="pt-2 pb-1 px-3 text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-semibold">{{ __('Reporting') }}</div>
                    <a href="{{ route('reporting') }}"
                       class="{{ $menuItem }} {{ request()->routeIs('reporting') ? $menuItemActive : $menuItemIdle }}">{{ __('Overview') }}</a>
                    <a href="{{ route('reporting.views') }}"
                       class="{{ $menuItem }} {{ request()->routeIs('reporting.views') ? $menuItemActive : $menuItemIdle }}">{{ __('Report views') }}</a>
                    <a href="{{ route('reporting.emails') }}"
                       class="{{ $menuItem }} {{ request()->routeIs('reporting.emails') ? $menuItemActive : $menuItemIdle }}">{{ __('Report emails') }}</a>

                    <div class="pt-2 pb-1 px-3 text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-semibold">{{ __('Workspace') }}</div>
                    <a href="{{ route('users') }}"
                       class="{{ $menuItem }} {{ request()->routeIs('users') ? $menuItemActive : $menuItemIdle }}">{{ __('Users') }}</a>
                    <a href="{{ route('webhooks') }}"
                       class="{{ $menuItem }} {{ request()->routeIs('webhooks') ? $menuItemActive : $menuItemIdle }}">{{ __('Webhooks') }}</a>

                    @if($aiEnabled)
                        <div class="pt-2 pb-1 px-3 text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-semibold">{{ __('AI') }}</div>
                        <a href="{{ route('ai.drafts') }}"
                           class="{{ $menuItem }} {{ request()->routeIs('ai.drafts') ? $menuItemActive : $menuItemIdle }}">{{ __('AI drafts') }}</a>
                        <a href="{{ route('settings.ai') }}"
                           class="{{ $menuItem }} {{ request()->routeIs('settings.ai') ? $menuItemActive : $menuItemIdle }}">{{ __('AI settings') }}</a>
                    @endif
                @else
                    <a href="{{ route('my-reports') }}"
                       class="{{ $menuItem }} {{ request()->routeIs('my-reports') ? $menuItemActive : $menuItemIdle }}">
                        {{ __('My reports') }}
                    </a>
                @endif

                <div class="border-t border-slate-200 dark:border-slate-800 mt-3 pt-3 flex items-center justify-between gap-3 sm:hidden">
                    <span class="inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold">
                            {{ auth()->user()->initials }}
                        </span>
                        {{ auth()->user()->name }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">{{ __('Sign out') }}</button>
                    </form>
                </div>
            </nav>
        </div>
    @endauth
</header>
