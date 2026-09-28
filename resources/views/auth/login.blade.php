<x-layouts.guest title="Masuk">
    <h2 class="mb-1 text-base font-semibold text-slate-900">Masuk ke akun Anda</h2>
    <p class="mb-5 text-sm text-slate-500">Registrasi publik dinonaktifkan. Akun dibuat oleh administrator.</p>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">
            {{ session('status') }}
        </div>
    @endif

    @error('email')
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
            {{ $message }}
        </div>
    @enderror

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-ui.input
            label="Email"
            name="email"
            type="email"
            value="{{ old('email') }}"
            required
            autofocus
            autocomplete="username" />

        <x-ui.input
            label="Password"
            name="password"
            type="password"
            required
            autocomplete="current-password" />

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
            Ingat saya
        </label>

        <x-ui.button type="submit" class="w-full">Masuk</x-ui.button>
    </form>
</x-layouts.guest>
