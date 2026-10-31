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

    {{-- Toast (window.toast / Livewire dispatch('toast')). Tetap tampil lintas wire:navigate. --}}
    <div x-data class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6" aria-live="polite">
        <template x-for="t in $store.toasts.items" :key="t.id">
            <div x-transition.opacity
                 class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg"
                 :class="{
                    'border-emerald-200 bg-emerald-600 text-white': t.type === 'success',
                    'border-red-200 bg-red-600 text-white': t.type === 'error',
                    'border-amber-300 bg-amber-50 text-amber-900': t.type === 'warning',
                    'border-slate-200 bg-white text-slate-800': t.type === 'info',
                 }" role="status">
                <div class="min-w-0 flex-1">
                    <p class="font-medium" x-text="t.message"></p>
                    <div class="mt-2 flex flex-wrap gap-2" x-show="t.actions.length">
                        <template x-for="a in t.actions" :key="a.label">
                            <a :href="a.href ?? '#'" :target="a.target ?? null"
                               @click="if (a.onClick) { $event.preventDefault(); a.onClick(); $store.toasts.remove(t.id) } else if (a.navigate) { $event.preventDefault(); Livewire.navigate(a.href) }"
                               class="rounded-md px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                               :class="t.type === 'success' || t.type === 'error' ? 'bg-white/15 ring-white/40 hover:bg-white/25' : 'bg-white ring-slate-300 hover:bg-slate-50'"
                               x-text="a.label"></a>
                        </template>
                    </div>
                </div>
                <button type="button" @click="$store.toasts.remove(t.id)" class="shrink-0 opacity-70 hover:opacity-100" aria-label="Tutup">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </template>
    </div>

    @livewireScripts
</body>
</html>
