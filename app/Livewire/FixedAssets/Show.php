<?php

namespace App\Livewire\FixedAssets;

use App\Domain\FixedAssets\PostDepreciation;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiJenis;
use App\Models\AsetTetap;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use AuthorizesRequests;

    public AsetTetap $aset;

    public ?int $depPeriodeId = null;

    public function mount(AsetTetap $aset): void
    {
        $this->aset = $aset;
    }

    public function postDepreciation(PostDepreciation $action): void
    {
        $this->authorize('perform-admin-mutation');
        $this->validate(['depPeriodeId' => ['required', 'integer', 'exists:periode_akuntansi,id']]);

        $periode = PeriodeAkuntansi::findOrFail($this->depPeriodeId);

        try {
            $action->handle($this->aset, $periode, auth()->user(), 'dep:'.$this->aset->id.':'.$periode->id, 'h');
            session()->flash('status', 'Penyusutan diposting.');
            $this->aset->refresh();
        } catch (DomainException $e) {
            $this->addError('dep', $e->getMessage());
        }
    }

    public function render()
    {
        $posted = Transaksi::where('sumber_tipe', 'aset_tetap')
            ->where('sumber_id', $this->aset->id)
            ->where('jenis_transaksi', TransaksiJenis::Penyusutan->value)
            ->whereNotNull('posted_at')->where('status', 'posted')
            ->orderBy('tanggal')->get();

        $akumulasi = Decimal::add((string) $this->aset->akumulasi_awal, (string) $posted->sum('total'));
        $nilaiBuku = Decimal::sub((string) $this->aset->harga_perolehan, $akumulasi);

        return view('livewire.fixed-assets.show', [
            'title' => $this->aset->kode_aset,
            'aset' => $this->aset,
            'akumulasi' => $akumulasi,
            'nilaiBuku' => $nilaiBuku,
            'postedDep' => $posted,
            'periods' => PeriodeAkuntansi::where('status', 'terbuka')->whereNotNull('opening_locked_at')
                ->orderBy('tahun')->orderBy('bulan')->get(),
        ]);
    }
}
