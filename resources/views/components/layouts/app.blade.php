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
                <span>{{ __('Open source · GPL-3.0') }}</span>
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
