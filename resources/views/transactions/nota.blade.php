@php
    use App\Enums\TransaksiStatus;
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $judul = match ($trx->jenis_transaksi->value) {
        'penjualan' => 'NOTA PENJUALAN',
        'pembelian' => 'NOTA PEMBELIAN',
        'pemasukan' => 'BUKTI KAS MASUK',
        'pengeluaran' => 'BUKTI KAS KELUAR',
        'angsuran' => 'BUKTI PEMBAYARAN ANGSURAN',
        'pencairan' => 'BUKTI PENCAIRAN PINJAMAN',
        default => 'BUKTI TRANSAKSI',
    };
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul }} {{ $trx->nomor_transaksi }}</title>
    <style>
        * { box-sizing: border-box; font-family: ui-sans-serif, system-ui, 'Segoe UI', Roboto, sans-serif; }
        body { margin: 0; padding: 24px; color: #111; font-size: 13px; background: #f1f5f9; }
        .sheet { max-width: 760px; margin: 0 auto; background: #fff; padding: 28px; border: 1px solid #e2e8f0; border-radius: 10px; }
        .kop { display: flex; justify-content: space-between; border-bottom: 2px solid #111; padding-bottom: 10px; }
        .kop h1 { margin: 0; font-size: 18px; }
        .kop p { margin: 2px 0; color: #475569; font-size: 11px; }
        h2 { text-align: center; margin: 16px 0 4px; font-size: 15px; letter-spacing: .05em; }
        .nomor { text-align: center; color: #475569; margin: 0 0 14px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; margin-bottom: 14px; }
        .meta div span { color: #64748b; display: inline-block; min-width: 96px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; }
        th { background: #f1f5f9; text-align: left; font-size: 12px; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        tfoot td { font-weight: 700; background: #f8fafc; }
        .stamp { display: inline-block; border: 2px solid #b45309; color: #b45309; padding: 2px 10px; font-weight: 700; border-radius: 4px; transform: rotate(-4deg); }
        .ttd { display: flex; justify-content: space-between; margin-top: 40px; text-align: center; }
        .ttd div { width: 40%; }
        .ttd .ruang { height: 64px; }
        .toolbar { max-width: 760px; margin: 0 auto 12px; display: flex; gap: 8px; justify-content: flex-end; }
        .toolbar button, .toolbar a { border: 1px solid #cbd5e1; background: #fff; padding: 6px 14px; border-radius: 8px; cursor: pointer; font-size: 13px; color: #111; text-decoration: none; }
        .toolbar button { background: #047857; color: #fff; border-color: #047857; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border: none; border-radius: 0; padding: 0; max-width: none; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('transactions.show', $trx) }}">Kembali</a>
        <button type="button" onclick="window.print()">Cetak</button>
    </div>

    <div class="sheet">
        <div class="kop">
            <div>
                <h1>{{ $org['nama'] }}</h1>
                <p>{{ $org['badan'] }} · {{ $org['desa'] }}, {{ $org['kecamatan'] }}, {{ $org['kabupaten'] }} – {{ $org['provinsi'] }}</p>
                @if ($trx->unitUsaha)<p>Unit: {{ $trx->unitUsaha->nama_unit }}</p>@endif
            </div>
            @if ($trx->status === TransaksiStatus::Draft)
                <div><span class="stamp">DRAFT</span></div>
            @elseif ($trx->status === TransaksiStatus::Reversed)
                <div><span class="stamp">DIBATALKAN</span></div>
            @endif
        </div>

        <h2>{{ $judul }}</h2>
        <p class="nomor">No. {{ $trx->nomor_transaksi }}</p>

        <div class="meta">
            <div><span>Tanggal</span>: {{ $trx->tanggal->format('d/m/Y') }}</div>
            <div><span>{{ $trx->jenis_transaksi->value === 'pembelian' ? 'Pemasok' : 'Pelanggan/Pihak' }}</span>: {{ $trx->pihak?->nama ?? '—' }}</div>
            @if ($trx->metode_pembayaran)
                <div><span>Pembayaran</span>: {{ $trx->metode_pembayaran === 'kredit' ? 'Kredit' : 'Tunai' }}</div>
            @endif
            @if ($trx->jatuh_tempo)
                <div><span>Jatuh tempo</span>: {{ $trx->jatuh_tempo->format('d/m/Y') }}</div>
            @endif
            @if ($kategori)
                <div><span>Kategori</span>: {{ $kategori }}</div>
            @endif
            @if ($trx->keterangan)
                <div style="grid-column: span 2"><span>Keterangan</span>: {{ $trx->keterangan }}</div>
            @endif
        </div>

        @if ($trx->barang->isNotEmpty())
            <table>
                <thead>
                    <tr><th style="width:32px">No</th><th>Barang / Jasa</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Diskon</th><th class="num">Jumlah</th></tr>
                </thead>
                <tbody>
                    @foreach ($trx->barang->sortBy('nomor_baris') as $b)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $b->produk?->nama_produk }}</td>
                            <td class="num">{{ rtrim(rtrim((string) $b->kuantitas, '0'), '.') }} {{ $b->produk?->satuan }}</td>
                            <td class="num">{{ $rp($b->harga) }}</td>
                            <td class="num">{{ (float) $b->diskon ? $rp($b->diskon) : '' }}</td>
                            <td class="num">{{ $rp((float) $b->kuantitas * (float) $b->harga - (float) $b->diskon + (float) $b->pajak) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="5" class="num">TOTAL</td><td class="num">{{ $rp($trx->total) }}</td></tr>
                </tfoot>
            </table>
        @else
            <table>
                <tbody>
                    <tr><td>Jumlah</td><td class="num" style="font-size:16px;font-weight:700">{{ $rp($trx->total) }}</td></tr>
                    @foreach ($trx->details as $d)
                        <tr><td>{{ (float) $d->debit ? 'Debet' : 'Kredit' }} — {{ $d->akun?->kode_akun }} {{ $d->akun?->nama_akun }}</td><td class="num">{{ $rp((float) $d->debit ?: $d->kredit) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="ttd">
            <div>
                <p>{{ $trx->jenis_transaksi->value === 'pembelian' ? 'Pemasok' : 'Penerima' }}</p>
                <div class="ruang"></div>
                <p>( ............................ )</p>
            </div>
            <div>
                <p>{{ $org['kota_ttd'] }}, {{ $trx->tanggal->format('d/m/Y') }}<br>{{ $org['jabatan_ttd'] }}</p>
                <div class="ruang"></div>
                <p><strong>{{ $trx->pembuat?->nama ?? '—' }}</strong></p>
            </div>
        </div>
    </div>
</body>
</html>
