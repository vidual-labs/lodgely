<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('lodgely.brand.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full text-slate-800 antialiased">
    <div class="min-h-full flex flex-col">
        <x-app.topbar />

        <main class="flex-1">
            <div class="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8 py-6">
                {{ $slot }}
            </div>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8 py-3 text-xs text-slate-500 flex justify-between">
                <span>{{ config('lodgely.brand.name') }} — {{ config('lodgely.brand.tagline') }}</span>
                <span>Open source · MIT</span>
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
            class="rounded-md bg-slate-900 text-slate-50 px-4 py-2 shadow-lg text-sm"
            x-text="msg"
        ></div>
    </div>

    @livewireScripts
</body>
</html>
