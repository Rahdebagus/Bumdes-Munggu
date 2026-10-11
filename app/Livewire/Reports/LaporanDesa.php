<?php

namespace App\Livewire\Reports;

use App\Domain\Reporting\LaporanDesaService;
use App\Domain\Shared\DomainException;
use App\Models\LaporanDesa as LaporanDesaModel;
use App\Models\PeriodeAkuntansi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Laporan Omzet Desa')]
class LaporanDesa extends Component
{
    use AuthorizesRequests;

    #[Url]
    public ?int $periodeId = null;

    public ?int $deliverId = null;

    public string $deliverKirim = '';

    public string $deliverTerima = '';

    public string $deliverBukti = '';

    public function mount(): void
    {
        $this->periodeId ??= PeriodeAkuntansi::whereNotNull('opening_locked_at')
            ->orderByDesc('tahun')->orderByDesc('bulan')->value('id');
    }

    public function periods()
    {
        return PeriodeAkuntansi::whereNotNull('opening_locked_at')
            ->orderByDesc('tahun')->orderByDesc('bulan')->get();
    }

    public function versions()
    {
        if (! $this->periodeId) {
            return collect();
        }

        return LaporanDesaModel::where('periode_id', $this->periodeId)
            ->with('approvedBy')->orderByDesc('version')->get();
    }

    private function run(callable $fn, string $ok): void
    {
        try {
            $fn();
            session()->flash('status', $ok);
        } catch (DomainException $e) {
            $this->addError('laporan', $e->getMessage());
        }
    }

    public function generate(): void
    {
        $this->authorize('perform-admin-mutation');
        $periode = PeriodeAkuntansi::findOrFail($this->periodeId);
        $this->run(fn () => app(LaporanDesaService::class)->generate($periode, auth()->user()), 'Draft laporan dibuat/diperbarui.');
    }

    public function submit(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $this->run(fn () => app(LaporanDesaService::class)->submit(LaporanDesaModel::findOrFail($id), auth()->user()), 'Laporan diajukan.');
    }

    public function approve(int $id): void
    {
        $this->authorize('approve-laporan');
        $this->run(fn () => app(LaporanDesaService::class)->approve(LaporanDesaModel::findOrFail($id), auth()->user()), 'Laporan disetujui.');
    }

    public function revise(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $this->run(fn () => app(LaporanDesaService::class)->revise(LaporanDesaModel::findOrFail($id), auth()->user()), 'Versi revisi dibuat.');
    }

    /** Buka panel catat pengiriman untuk laporan yang sudah disetujui. */
    public function startDelivery(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $l = LaporanDesaModel::findOrFail($id);
        $this->deliverId = $id;
        $this->deliverKirim = $l->tanggal_dikirim?->toDateString() ?? now()->toDateString();
        $this->deliverTerima = $l->tanggal_diterima?->toDateString() ?? '';
        $this->deliverBukti = (string) ($l->bukti_penerimaan ?? '');
    }

    /** Catat tanggal kirim/terima + bukti penerimaan (tanpa mengubah nominal). */
    public function recordDelivery(): void
    {
        $this->authorize('perform-admin-mutation');
        $this->validate([
            'deliverKirim' => ['nullable', 'date'],
            'deliverTerima' => ['nullable', 'date', 'after_or_equal:deliverKirim'],
            'deliverBukti' => ['nullable', 'string', 'max:255'],
        ]);

        $this->run(function () {
            app(LaporanDesaService::class)->recordDelivery(
                LaporanDesaModel::findOrFail($this->deliverId),
                auth()->user(),
                [
                    'tanggal_dikirim' => $this->deliverKirim ?: null,
                    'tanggal_diterima' => $this->deliverTerima ?: null,
                    'bukti_penerimaan' => $this->deliverBukti ?: null,
                ],
            );
            $this->deliverId = null;
        }, 'Status pengiriman laporan dicatat.');
    }

    public function render()
    {
        return view('livewire.reports.laporan-desa');
    }
}
