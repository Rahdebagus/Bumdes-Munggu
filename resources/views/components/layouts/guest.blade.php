<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Masuk' }} — SIA BUMDes Munggu</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full items-center justify-center bg-slate-100 px-4 py-12 text-slate-800 antialiased">
    <div class="w-full max-w-md">
        <div class="mb-6 flex flex-col items-center gap-2">
            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-600 text-lg font-bold text-white">B</span>
            <h1 class="text-lg font-semibold text-slate-900">SIA BUMDes Munggu</h1>
            <p class="text-sm text-slate-500">Sistem Informasi Akuntansi</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
