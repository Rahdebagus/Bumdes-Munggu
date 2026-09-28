<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'SIA BUMDes Munggu' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
    <div x-data="{ sidebarOpen: false }" class="min-h-full">
        <x-app.sidebar />

        <!-- Overlay sidebar mobile -->
        <div x-show="sidebarOpen" x-cloak
             class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
             @click="sidebarOpen = false"></div>

        <div class="lg:pl-64">
            <x-app.header :title="$title ?? 'Dashboard'" :breadcrumb="$breadcrumb ?? []" />

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                @if (session('status'))
                    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                         role="status">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="mx-auto max-w-7xl">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
