<?php

namespace App\Livewire\Dashboard;

use App\Domain\Reporting\ReportService;
use App\Enums\StokOpnameStatus;
use App\Models\PeriodeAkuntansi;
use App\Models\StokOpname;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Index extends Component
{
    use AuthorizesRequests;

    /**
     * KPI dihitung dari ReportService (ledger efektif), bukan angka acak atau
     * hardcoded (dokumen07 §Dashboard, dokumen10). Tanpa periode ber-opening
     * terkunci → empty state jujur ('—').
     *
     * @return array<int, array{label: string, value: string, hint: string}>
     */
    public function kpis(): array
    {
        $window = $this->window();

        if ($window === null) {
            return [
                ['label' => 'Saldo Kas & Bank', 'value' => '—', 'hint' => 'Belum ada periode dengan saldo awal terkunci'],
                ['label' => 'Omzet Dagang', 'value' => '—', 'hint' => 'Menunggu transaksi terposting'],
                ['label' => 'Pendapatan Kredit', 'value' => '—', 'hint' => 'Menunggu transaksi terposting'],
                ['label' => 'Laba Bersih', 'value' => '—', 'hint' => 'Menunggu transaksi terposting'],
            ];
        }

        [$from, $to, $label] = $window;
        $svc = app(ReportService::class);
        $neraca = $svc->neraca($to, cutoverFrom: $from);
        $lr = $svc->labaRugi($from, $to);

        return [
            ['label' => 'Saldo Kas & Bank', 'value' => $this->rupiah($neraca['kas_bank']), 'hint' => "Posisi s/d {$label}"],
            ['label' => 'Omzet Dagang', 'value' => $this->rupiah($lr['penjualan_bersih']), 'hint' => "Penjualan bersih {$label}"],
            ['label' => 'Pendapatan Kredit', 'value' => $this->rupiah($lr['pendapatan_kredit']), 'hint' => "Bunga + denda {$label}"],
            ['label' => 'Laba Bersih', 'value' => $this->rupiah($lr['laba_bersih']), 'hint' => "Akumulasi {$label}"],
        ];
    }

    /**
     * Ringkasan laba-rugi untuk panel tren (pendapatan vs beban), dari periode
     * aktif. Null bila belum ada data.
     *
     * @return array<string, string>|null
     */
    public function ringkasan(): ?array
    {
        $window = $this->window();
        if ($window === null) {
            return null;
        }

        [$from, $to] = $window;

        return app(ReportService::class)->labaRugi($from, $to);
    }

    /**
     * Antrean approval pimpinan: stok opname menunggu persetujuan.
     *
     * @return Collection<int, StokOpname>
     */
    public function antrean()
    {
        return StokOpname::where('status', StokOpnameStatus::MenungguPersetujuan->value)
            ->with('unitUsaha')
            ->latest('tanggal')
            ->limit(10)
            ->get();
    }

    /**
     * Jendela pelaporan: dari cutover terkunci paling awal s/d akhir periode
     * terbaru. Null bila belum ada opening terkunci.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function window(): ?array
    {
        $cutover = PeriodeAkuntansi::whereNotNull('opening_locked_at')
            ->orderBy('tanggal_mulai')
            ->first();

        if ($cutover === null) {
            return null;
        }

        $terbaru = PeriodeAkuntansi::orderByDesc('tahun')->orderByDesc('bulan')->first();

        return [
            $cutover->tanggal_mulai->toDateString(),
            $terbaru->tanggal_selesai->toDateString(),
            str_pad((string) $terbaru->bulan, 2, '0', STR_PAD_LEFT).'/'.$terbaru->tahun,
        ];
    }

    private function rupiah(string $value): string
    {
        return 'Rp '.number_format((float) $value, 2, ',', '.');
    }

    /**
     * Mutation khusus Admin dengan policy server (Pimpinan tertolak meski tombol
     * tidak terlihat). Pola untuk seluruh mutation Livewire.
     */
    public function clearReportCache(): void
    {
        $this->authorize('perform-admin-mutation');

        Cache::flush();

        session()->flash('status', 'Cache aplikasi dibersihkan.');
    }

    public function render()
    {
        return view('livewire.dashboard.index');
    }
}
