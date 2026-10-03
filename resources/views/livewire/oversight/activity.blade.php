<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Histori Aktivitas</h1>
            <p class="text-sm text-slate-500">Jejak audit append-only: posting, reverse, lock, approval, tutup/buka periode.</p>
        </div>
        <label class="block">
            <span class="text-xs font-medium text-slate-500">Modul</span>
            <select wire:model.live="modul" class="mt-1 rounded-lg border-slate-300 text-sm">
                <option value="">Semua modul</option>
                @foreach ($this->moduls() as $m)
                    <option value="{{ $m }}">{{ $m }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <x-ui.card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                        <th class="px-3 py-2">Waktu</th>
                        <th class="px-3 py-2">Pengguna</th>
                        <th class="px-3 py-2">Modul</th>
                        <th class="px-3 py-2">Aksi</th>
                        <th class="px-3 py-2">Referensi</th>
                        <th class="px-3 py-2">Alasan/Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-2 whitespace-nowrap text-slate-500 tabular-nums">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $log->user?->nama ?? '—' }}</td>
                            <td class="px-3 py-2"><x-ui.badge color="slate">{{ $log->modul }}</x-ui.badge></td>
                            <td class="px-3 py-2 text-slate-700">{{ $log->aksi }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $log->referensi_tipe }}#{{ $log->referensi_id }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $log->alasan }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-6 text-center text-sm text-slate-400">Belum ada aktivitas tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $logs->links() }}</div>
    </x-ui.card>
</div>
