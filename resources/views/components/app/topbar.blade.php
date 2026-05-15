<header class="sticky top-0 z-30 border-b border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm">
    <div class="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
        <div class="flex items-center gap-6">
            <a href="{{ route('inbox') }}" class="flex items-center gap-2 group">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-brand-900 text-white text-xs font-bold shadow-sm group-hover:shadow-md transition-shadow">L</span>
                <span class="text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ config('lodgely.brand.name') }}</span>
            </a>

            <nav class="hidden md:flex items-center gap-0.5 text-sm">
                <a href="{{ route('inbox') }}"
                   class="px-3 py-1.5 rounded-lg transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 {{ request()->routeIs('inbox') ? 'text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-800 font-medium' : 'text-slate-600 dark:text-slate-400' }}">
                    {{ __('Inbox') }}
                </a>
                @auth
                    @if(auth()->user()->isOperator())
                        <a href="{{ route('imports.csv') }}"
                           class="px-3 py-1.5 rounded-lg transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 {{ request()->routeIs('imports.csv') ? 'text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-800 font-medium' : 'text-slate-600 dark:text-slate-400' }}">
                            {{ __('CSV import') }}
                        </a>
                        <a href="{{ route('imports.email') }}"
                           class="px-3 py-1.5 rounded-lg transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 {{ request()->routeIs('imports.email') ? 'text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-800 font-medium' : 'text-slate-600 dark:text-slate-400' }}">
                            {{ __('Email (mock)') }}
                        </a>
                        @if(config('lodgely.importers.email.imap.host'))
                        <a href="{{ route('imports.email-imap') }}"
                           class="px-3 py-1.5 rounded-lg transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 {{ request()->routeIs('imports.email-imap') ? 'text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-800 font-medium' : 'text-slate-600 dark:text-slate-400' }}">
                            {{ __('Email (IMAP)') }}
                        </a>
                        @endif
                        <a href="{{ route('users') }}"
                           class="px-3 py-1.5 rounded-lg transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 {{ request()->routeIs('users') ? 'text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-800 font-medium' : 'text-slate-600 dark:text-slate-400' }}">
                            {{ __('Users') }}
                        </a>
                        <a href="{{ route('webhooks') }}"
                           class="px-3 py-1.5 rounded-lg transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 {{ request()->routeIs('webhooks') ? 'text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-800 font-medium' : 'text-slate-600 dark:text-slate-400' }}">
                            {{ __('Webhooks') }}
                        </a>
                    @endif
                @endauth
            </nav>
        </div>

        <div class="flex items-center gap-2">
            {{-- Dark / Light mode pill switch --}}
            <div x-data="{ dark: document.documentElement.classList.contains('dark') }"
                 class="flex items-center rounded-lg bg-slate-100 dark:bg-slate-800/80 p-0.5 text-xs font-medium">
                <button @click="dark = false; document.documentElement.classList.remove('dark'); localStorage.setItem('theme', 'light')"
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
                    <span class="hidden sm:inline">{{ __('Light') }}</span>
                </button>
                <button @click="dark = true; document.documentElement.classList.add('dark'); localStorage.setItem('theme', 'dark')"
                        :class="dark ? 'bg-slate-700 shadow-sm text-slate-100' : 'text-slate-500 dark:text-slate-400'"
                        class="flex items-center gap-1 px-2.5 py-1 rounded-md transition-all"
                        :aria-pressed="dark ? 'true' : 'false'"
                        aria-label="{{ __('Switch to dark mode') }}">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <span class="hidden sm:inline">{{ __('Dark') }}</span>
                </button>
            </div>

            {{-- Language switcher --}}
            <div class="flex items-center rounded-lg bg-slate-100 dark:bg-slate-800/80 p-0.5 text-xs font-medium">
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
                    <span class="hidden sm:inline-flex items-center gap-2 text-slate-600 dark:text-slate-400">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold">
                            {{ auth()->user()->initials }}
                        </span>
                        <span>
                            {{ auth()->user()->name }}
                            <span class="text-xs text-slate-400 dark:text-slate-600">· {{ auth()->user()->role->label() }}</span>
                        </span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors text-sm">{{ __('Sign out') }}</button>
                    </form>
                </div>
            @endauth
        </div>
    </div>
</header>
