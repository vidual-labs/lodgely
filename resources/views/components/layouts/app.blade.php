<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('lodgely.brand.name') }}</title>
    <script>
        /* Apply dark class immediately to prevent flash of unstyled content.
           For authenticated users the server-known theme wins; guests fall back to localStorage / OS. */
        @auth
        (function () {
            var theme = '{{ auth()->user()->ui_theme }}';
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
        @else
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
        @endauth
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 antialiased">
    <div class="min-h-full flex flex-col">
        <x-app.topbar />

        <main class="flex-1">
            <div class="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8 py-6">
                {{ $slot }}
            </div>
        </main>

        <footer class="border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
            <div class="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8 py-3 text-xs text-slate-500 flex flex-col sm:flex-row gap-1 sm:gap-3 sm:justify-between">
                <span>{{ config('lodgely.brand.name') }} — {{ config('lodgely.brand.tagline') }}</span>
                <span class="inline-flex items-center gap-2">
                    <span>{{ __('Open source · GPL-3.0') }}</span>
                    <span class="text-slate-300 dark:text-slate-700" aria-hidden="true">·</span>
                    <span class="font-mono tabular-nums">v{{ config('lodgely.version') }}</span>
                    @if(config('lodgely.brand.github_url'))
                        <span class="text-slate-300 dark:text-slate-700" aria-hidden="true">·</span>
                        <a href="{{ config('lodgely.brand.github_url') }}"
                           target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors"
                           aria-label="GitHub">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.39 7.86 10.91.58.11.79-.25.79-.56 0-.28-.01-1.02-.02-2-3.2.7-3.87-1.54-3.87-1.54-.52-1.33-1.28-1.68-1.28-1.68-1.05-.72.08-.7.08-.7 1.16.08 1.77 1.19 1.77 1.19 1.03 1.77 2.7 1.26 3.36.96.1-.75.4-1.26.73-1.55-2.55-.29-5.24-1.28-5.24-5.69 0-1.26.45-2.29 1.18-3.1-.12-.29-.51-1.46.11-3.05 0 0 .97-.31 3.18 1.18a11 11 0 0 1 5.78 0c2.2-1.49 3.17-1.18 3.17-1.18.63 1.59.23 2.76.11 3.05.74.81 1.18 1.84 1.18 3.1 0 4.43-2.69 5.4-5.26 5.68.41.36.78 1.07.78 2.16 0 1.56-.01 2.82-.01 3.21 0 .31.21.68.8.56C20.21 21.39 23.5 17.08 23.5 12 23.5 5.65 18.35.5 12 .5Z"/>
                            </svg>
                            <span>GitHub</span>
                        </a>
                    @endif
                </span>
            </div>
        </footer>
    </div>

    <div
        x-data="{ msg: '', show: false }"
        x-on:toast.window="msg = $event.detail.message; show = true; setTimeout(() => show = false, 3500)"
        x-cloak
        role="status" aria-live="polite" aria-atomic="true"
        class="fixed bottom-6 right-6 z-50"
    >
        <div
            x-show="show"
            x-transition.opacity
            class="rounded-xl bg-slate-900 dark:bg-slate-700 text-slate-50 px-4 py-2.5 shadow-xl text-sm"
            x-text="msg"
        ></div>
    </div>

    @livewireScripts
</body>
</html>
