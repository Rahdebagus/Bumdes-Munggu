<?php

namespace App\Livewire\FixedAssets;

use App\Domain\FixedAssets\PostDepreciation;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Models\AsetTetap;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Penyusutan Aset')]
class Depreciation extends Component
{
    use AuthorizesRequests;

    #[Url]
    public ?int $periodeId = null;

    public function mount(): void
    {
        $this->periodeId ??= PeriodeAkuntansi::orderByDesc('tahun')->orderByDesc('bulan')->value('id');
    }

    public function periods()
    {
        return PeriodeAkuntansi::orderByDesc('tahun')->orderByDesc('bulan')->get();
    }

    /** Baris aset dengan akumulasi, nilai buku, dan status penyusutan periode terpilih. */
    public function rows(): array
    {
        $periode = PeriodeAkuntansi::find($this->periodeId);
        if (! $periode) {
            return [];
        }
        $to = $periode->tanggal_selesai->toDateString();

        return AsetTetap::with('unitUsaha')->orderBy('kode_aset')->get()->map(function ($a) use ($periode, $to) {
            $basis = Decimal::sub((string) $a->harga_perolehan, (string) $a->nilai_residu);
            $akumSebelum = Decimal::add((string) $a->akumulasi_awal, (string) Transaksi::where('sumber_tipe', 'aset_tetap')
                ->where('sumber_id', $a->id)->where('jenis_transaksi', 'penyusutan')
                ->whereNotNull('posted_at')->where('status', 'posted')
                ->whereDate('tanggal', '<=', $to)->sum('total'));
            $sudahPeriodeIni = Transaksi::where('posting_key', "DEP:{$a->id}:{$periode->id}")
                ->whereNotNull('posted_at')->where('status', 'posted')->exists();
            $mulai = $a->tanggal_mulai_susut?->toDateString() ?? $a->tanggal_perolehan->toDateString();

            $penuh = Decimal::compare($akumSebelum, $basis) >= 0;
            $belumMulai = $mulai > $to;
            $eligible = $a->status->value === 'aktif' && ! $penuh && ! $belumMulai && ! $sudahPeriodeIni;

            return [
                'id' => $a->id,
                'kode' => $a->kode_aset,
                'nama' => $a->nama_aset,
                'unit' => $a->unitUsaha?->nama_unit ?? '—',
                'perolehan' => (string) $a->harga_perolehan,
                'akumulasi' => $akumSebelum,
                'nilai_buku' => Decimal::sub((string) $a->harga_perolehan, $akumSebelum),
                'sudah' => $sudahPeriodeIni,
                'eligible' => $eligible,
                'keterangan' => $sudahPeriodeIni ? 'Sudah diposting' : ($penuh ? 'Tersusut penuh' : ($belumMulai ? 'Belum mulai susut' : ($a->status->value !== 'aktif' ? 'Nonaktif' : 'Siap diposting'))),
            ];
        })->all();
    }

    public function post(int $asetId): void
    {
        $this->authorize('perform-admin-mutation');
        $aset = AsetTetap::findOrFail($asetId);
        $periode = PeriodeAkuntansi::findOrFail($this->periodeId);

        try {
            app(PostDepreciation::class)->handle($aset, $periode, auth()->user(), "dep:{$aset->id}:{$periode->id}", 'h');
            session()->flash('status', "Penyusutan {$aset->kode_aset} untuk periode ini diposting.");
        } catch (DomainException $e) {
            $this->addError('dep', $e->getMessage());
        }
    }

    public function postAllEligible(): void
    {
        $this->authorize('perform-admin-mutation');
        $periode = PeriodeAkuntansi::findOrFail($this->periodeId);
        $ok = 0;
        $gagal = 0;

        foreach ($this->rows() as $row) {
            if (! $row['eligible']) {
                continue;
            }
            try {
                app(PostDepreciation::class)->handle(AsetTetap::findOrFail($row['id']), $periode, auth()->user(), "dep:{$row['id']}:{$periode->id}", 'h');
                $ok++;
            } catch (DomainException) {
                $gagal++;
            }
        }

        session()->flash('status', "Penyusutan batch: {$ok} diposting".($gagal ? ", {$gagal} dilewati" : '').'.');
    }

    public function render()
    {
        $periode = PeriodeAkuntansi::find($this->periodeId);
        $label = $periode ? Carbon::create($periode->tahun, $periode->bulan, 1)->translatedFormat('F Y') : '—';

        return view('livewire.fixed-assets.depreciation', [
            'rows' => $this->rows(),
            'periodeLabel' => $label,
            'periodeTutup' => $periode && $periode->status->value === 'ditutup',
        ]);
    }
}
