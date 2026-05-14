<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
        <div class="flex items-center gap-8">
            <a href="{{ route('inbox') }}" class="flex items-center gap-2">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-slate-900 text-white text-xs font-bold">L</span>
                <span class="text-sm font-semibold tracking-tight text-slate-900">{{ config('lodgely.brand.name') }}</span>
            </a>

            <nav class="hidden md:flex items-center gap-1 text-sm">
                <a href="{{ route('inbox') }}"
                   class="px-3 py-1.5 rounded-md hover:bg-slate-100 {{ request()->routeIs('inbox') ? 'text-slate-900 bg-slate-100 font-medium' : 'text-slate-600' }}">
                    Inbox
                </a>
                @auth
                    @if(auth()->user()->isOperator())
                        <a href="{{ route('imports.csv') }}"
                           class="px-3 py-1.5 rounded-md hover:bg-slate-100 {{ request()->routeIs('imports.csv') ? 'text-slate-900 bg-slate-100 font-medium' : 'text-slate-600' }}">
                            CSV import
                        </a>
                        <a href="{{ route('imports.email') }}"
                           class="px-3 py-1.5 rounded-md hover:bg-slate-100 {{ request()->routeIs('imports.email') ? 'text-slate-900 bg-slate-100 font-medium' : 'text-slate-600' }}">
                            Email (mock)
                        </a>
                        <a href="{{ route('users') }}"
                           class="px-3 py-1.5 rounded-md hover:bg-slate-100 {{ request()->routeIs('users') ? 'text-slate-900 bg-slate-100 font-medium' : 'text-slate-600' }}">
                            Users
                        </a>
                    @endif
                @endauth
            </nav>
        </div>

        @auth
            <div class="flex items-center gap-3 text-sm">
                <span class="hidden sm:inline-flex items-center gap-2 text-slate-600">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-700 text-xs font-semibold">
                        {{ auth()->user()->initials }}
                    </span>
                    <span>
                        {{ auth()->user()->name }}
                        <span class="text-xs text-slate-400">· {{ auth()->user()->role->label() }}</span>
                    </span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-500 hover:text-slate-900">Sign out</button>
                </form>
            </div>
        @endauth
    </div>
</header>
