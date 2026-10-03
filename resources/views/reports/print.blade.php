<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $data['title'] }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 0; }
        .kop { border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .kop h1 { font-size: 16px; margin: 0; }
        .kop p { margin: 2px 0; font-size: 10px; color: #444; }
        .judul { text-align: center; margin: 10px 0; }
        .judul h2 { font-size: 14px; margin: 0; }
        .judul p { margin: 2px 0; font-size: 10px; color: #333; }
        .draft { color: #b45309; font-weight: bold; border: 1px solid #b45309; padding: 1px 6px; border-radius: 4px; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; }
        th { background: #f1f5f9; text-align: left; font-size: 10px; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        tfoot td { font-weight: bold; background: #f8fafc; }
        .ttd { margin-top: 36px; width: 100%; }
        .ttd td { border: none; text-align: center; font-size: 10px; height: 60px; vertical-align: top; }
        .meta { margin-top: 6px; font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>BUMDes Munggu</h1>
        <p>Sistem Informasi Akuntansi — Desa Munggu</p>
    </div>

    <div class="judul">
        <h2>{{ $data['title'] }}</h2>
        <p>Periode {{ $data['periode'] }} · {{ $data['unit'] }}</p>
        <p>Dicetak {{ $data['generated_at'] }} · <span class="draft">{{ $data['status_label'] ?? 'DRAFT — BELUM DISAHKAN' }}</span></p>
    </div>

    <table>
        <thead>
            <tr><th>Keterangan</th><th style="text-align:right">Nilai (Rp)</th></tr>
        </thead>
        <tbody>
            @foreach ($data['summary'] as $item)
                <tr>
                    <td>{{ $item['label'] }}</td>
                    <td class="num">{{ number_format((float) $item['value'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (($data['detail'] ?? null) !== null)
        <h3 style="font-size:12px; margin-top:16px;">Rincian</h3>
        <table>
            <thead>
                <tr>
                    @foreach ($data['detail']['headers'] as $h)
                        <th>{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($data['detail']['rows'] as $row)
                    <tr>
                        @foreach ($row as $cell)
                            @if (array_key_exists('num', $cell))
                                <td class="num">{{ number_format((float) $cell['num'], 2, ',', '.') }}</td>
                            @else
                                <td>{{ $cell['text'] ?? '' }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if (($data['detail']['foot'] ?? null) !== null)
                <tfoot>
                    <tr>
                        @foreach ($data['detail']['foot'] as $cell)
                            @if (array_key_exists('num', $cell))
                                <td class="num">{{ number_format((float) $cell['num'], 2, ',', '.') }}</td>
                            @else
                                <td>{{ $cell['text'] ?? '' }}</td>
                            @endif
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif

    <table class="ttd">
        <tr>
            <td>Disiapkan,<br><br><br>( Admin )</td>
            <td>Diperiksa,<br><br><br>( Pengawas )</td>
            <td>Disetujui,<br><br><br>( Pimpinan )</td>
        </tr>
    </table>

    <p class="meta">Dokumen ini dihasilkan dari jurnal terposting dan saldo awal terkunci. Angka resmi mengikuti data server; tanda tangan bersifat ruang isian manual, bukan persetujuan elektronik.</p>
</body>
</html>
