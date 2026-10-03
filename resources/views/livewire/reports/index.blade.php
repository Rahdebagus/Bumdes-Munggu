<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Laporan & Rekonsiliasi</h1>
        <p class="text-sm text-slate-500">Angka dari jurnal terposting + saldo awal terkunci. HTML, PDF, dan XLSX memakai dataset yang sama.</p>
    </div>

    <x-ui.card>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <label class="block">
                <span class="text-xs font-medium text-slate-500">Laporan</span>
                <select wire:model.live="type" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    @foreach ($this->typeOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-xs font-medium text-slate-500">Dari</span>
                <input type="date" wire:model.live="from" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
            </label>
            <label class="block">
                <span class="text-xs font-medium text-slate-500">Sampai</span>
                <input type="date" wire:model.live="to" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
            </label>
            <label class="block">
                <span class="text-xs font-medium text-slate-500">Unit</span>
                <select wire:model.live="unitId" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Konsolidasi</option>
                    @foreach ($this->units() as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->nama_unit }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end gap-2">
                <a href="{{ route('reports.export', ['type' => $type, 'format' => 'pdf', 'from' => $from, 'to' => $to, 'unitId' => $unitId]) }}"
                   class="inline-flex items-center rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700">PDF</a>
                <a href="{{ route('reports.export', ['type' => $type, 'format' => 'xlsx', 'from' => $from, 'to' => $to, 'unitId' => $unitId]) }}"
                   class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">XLSX</a>
            </div>
        </div>
    </x-ui.card>

    @php($data = $this->dataset())
    <x-ui.card :title="$data['title']" :subtitle="'Periode '.$data['periode'].' · '.$data['unit']">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                    <th class="px-3 py-2">Keterangan</th>
                    <th class="px-3 py-2 text-right">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['summary'] as $item)
                    <tr class="border-b border-slate-100">
                        <td class="px-3 py-2 text-slate-700">{{ $item['label'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-slate-900">{{ number_format((float) $item['value'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (($data['detail'] ?? null) !== null)
            <h3 class="mt-6 mb-2 text-sm font-semibold text-slate-700">Rincian</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                            @foreach ($data['detail']['headers'] as $h)
                                <th class="px-3 py-2">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['detail']['rows'] as $row)
                            <tr class="border-b border-slate-100">
                                @foreach ($row as $cell)
                                    @if (array_key_exists('num', $cell))
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $cell['num'], 2, ',', '.') }}</td>
                                    @else
                                        <td class="px-3 py-2 text-slate-600">{{ $cell['text'] ?? '' }}</td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card title="Rekonsiliasi" subtitle="Selisih dibandingkan tepat 2 desimal (toleransi 0,00)">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                    <th class="px-3 py-2">Pemeriksaan</th>
                    <th class="px-3 py-2 text-right">Diharapkan</th>
                    <th class="px-3 py-2 text-right">Aktual</th>
                    <th class="px-3 py-2 text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->reconciliation() as $c)
                    <tr class="border-b border-slate-100">
                        <td class="px-3 py-2 text-slate-700">{{ $c['label'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-slate-500">{{ number_format((float) $c['expected'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-slate-500">{{ number_format((float) $c['actual'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-center">
                            @if ($c['match'])
                                <x-ui.badge color="emerald">OK</x-ui.badge>
                            @else
                                <x-ui.badge color="red">{{ $c['severity'] }}</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @foreach ($this->warnings() as $w)
                    <tr class="border-b border-slate-100">
                        <td class="px-3 py-2 text-slate-700">{{ $w['label'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-slate-400">—</td>
                        <td class="px-3 py-2 text-right tabular-nums text-slate-500">{{ $w['count'] }}</td>
                        <td class="px-3 py-2 text-center">
                            @if ($w['count'] === 0)
                                <x-ui.badge color="emerald">OK</x-ui.badge>
                            @else
                                <x-ui.badge color="amber">{{ $w['severity'] }}</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>
</div>
