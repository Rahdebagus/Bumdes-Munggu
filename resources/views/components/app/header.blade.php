@props([
    'title' => 'Dashboard',
    'breadcrumb' => [],
])

<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">
    <button type="button"
            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
            @click="sidebarOpen = true"
            aria-label="Buka menu">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </button>

    <div class="min-w-0 flex-1">
        <x-ui.breadcrumb :items="$breadcrumb" />
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ $title }}</h1>
    </div>

    @auth
        <div x-data="{ open: false }" class="relative">
            <button type="button"
                    class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-100"
                    @click="open = ! open" :aria-expanded="open" aria-haspopup="true">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-700">
                    {{ \Illuminate\Support\Str::of(auth()->user()->nama)->substr(0, 1)->upper() }}
                </span>
                <span class="hidden text-left sm:block">
                    <span class="block font-medium text-slate-900">{{ auth()->user()->nama }}</span>
                    <span class="block text-xs text-slate-500">{{ auth()->user()->role->label() }}</span>
                </span>
            </button>

            <div x-show="open" x-cloak @click.outside="open = false"
                 x-transition
                 class="absolute right-0 mt-2 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                 role="menu">
                <div class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
                    {{ auth()->user()->email }}
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-100"
                            role="menuitem">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    @endauth
</header>
