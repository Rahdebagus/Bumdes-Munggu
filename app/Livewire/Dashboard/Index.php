<?php

namespace App\Livewire\Dashboard;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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
     * Dashboard tahap01 sengaja kosong: belum ada modul data. KPI menampilkan
     * nol/empty state jujur, bukan angka acak (dokumen07 §Dashboard).
     *
     * @return array<int, array{label: string, value: string, hint: string}>
     */
    public function kpis(): array
    {
        return [
            ['label' => 'Saldo Kas & Bank', 'value' => '—', 'hint' => 'Menunggu modul kas'],
            ['label' => 'Omzet Dagang', 'value' => '—', 'hint' => 'Menunggu modul dagang'],
            ['label' => 'Pendapatan Kredit', 'value' => '—', 'hint' => 'Menunggu modul pinjaman'],
            ['label' => 'Laba Bersih', 'value' => '—', 'hint' => 'Menunggu laporan'],
        ];
    }

    /**
     * Contoh public mutation method dengan policy server (tahap01).
     * Hanya Admin; Pimpinan tertolak di server meski tombol tidak terlihat.
     * Menjadi pola untuk seluruh mutation Livewire pada tahap berikutnya.
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
