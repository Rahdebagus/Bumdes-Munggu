<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Bukti Transaksi</h1>
            <p class="text-sm text-slate-500">Register bukti dan status verifikasi. Bukti belum valid memblokir approval omzet.</p>
        </div>
        <label class="block">
            <span class="text-xs font-medium text-slate-500">Status</span>
            <select wire:model.live="status" class="mt-1 rounded-lg border-slate-300 text-sm">
                <option value="">Semua</option>
                <option value="belum_diperiksa">Belum diperiksa</option>
                <option value="valid">Valid</option>
                <option value="ditolak">Ditolak</option>
            </select>
        </label>
    </div>

    <x-ui.card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                        <th class="px-3 py-2">Nomor</th>
                        <th class="px-3 py-2">Transaksi</th>
                        <th class="px-3 py-2">Jenis</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Pengunggah</th>
                        <th class="px-3 py-2">Verifikator</th>
                        <th class="px-3 py-2 text-right">Unduh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bukti as $b)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2 font-medium text-slate-800">{{ $b->nomor_bukti }}</td>
                            <td class="px-3 py-2 text-xs">
                                <a href="{{ route('transactions.show', $b->transaksi_id) }}" wire:navigate class="text-indigo-600 hover:underline">
                                    {{ $b->transaksi?->nomor_transaksi ?? ('#'.$b->transaksi_id) }}
                                </a>
                            </td>
                            <td class="px-3 py-2 text-slate-600">{{ $b->jenis_bukti }}</td>
                            <td class="px-3 py-2">
                                @php($c = match ($b->status_verifikasi) { 'valid' => 'emerald', 'ditolak' => 'red', default => 'amber' })
                                <x-ui.badge :color="$c">{{ $b->status_verifikasi }}</x-ui.badge>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $b->uploadedBy?->nama ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $b->verifiedBy?->nama ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('evidence.download', $b->id) }}" class="text-xs text-indigo-600 hover:underline">Unduh</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-6 text-center text-sm text-slate-400">Belum ada bukti terunggah.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $bukti->links() }}</div>
    </x-ui.card>
</div>
