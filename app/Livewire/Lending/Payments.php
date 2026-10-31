<?php

namespace App\Livewire\Lending;

use App\Domain\Accounting\PostDocument;
use App\Domain\Accounting\PostingService;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\Lending\SaveInstallmentPayment;
use App\Domain\OpeningBalance\OpeningItems;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Enums\SaldoRincianJenis;
use App\Models\Akun;
use App\Models\Angsuran;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Pembayaran Angsuran')]
class Payments extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $q = '';

    public ?int $akunKasId = null;

    public string $payTanggal = '';

    public ?int $payAngsuranId = null;

    public string $payPokok = '0';

    public string $payBunga = '0';

    public string $payDenda = '0';

    // Setoran borongan pinjaman pedagang lama (rincian saldo awal), seperti slip
    // kas masuk kredit: per nasabah pokok/bunga/denda dalam satu dokumen kas.
    public string $lamaTanggal = '';

    public ?int $lamaKasId = null;

    /** @var array<int, string> rincian id => pokok */
    public array $lamaPokok = [];

    /** @var array<int, string> rincian id => bunga */
    public array $lamaBunga = [];

    /** @var array<int, string> rincian id => denda */
    public array $lamaDenda = [];

    public function mount(): void
    {
        $this->payTanggal = $this->lamaTanggal = now()->toDateString();
        $this->akunKasId = $this->lamaKasId = (int) Akun::where('system_key', 'cash')->value('id') ?: null;
    }

    /** Pinjaman lama dari saldo awal yang masih bersisa (difilter pencarian). */
    public function pinjamanLama(): Collection
    {
        $q = mb_strtolower($this->q);

        return app(OpeningItems::class)->outstanding(SaldoRincianJenis::Pinjaman)
            ->filter(fn ($o) => $q === ''
                || str_contains(mb_strtolower($o['rincian']->kode_referensi), $q)
                || str_contains(mb_strtolower($o['rincian']->pihak?->nama ?? ''), $q))
            ->values();
    }

    public function totalLama(): string
    {
        $total = '0';
        foreach ([$this->lamaPokok, $this->lamaBunga, $this->lamaDenda] as $kolom) {
            foreach ($kolom as $v) {
                if (is_numeric($v) && (float) $v > 0) {
                    $total = Decimal::add($total, (string) $v);
                }
            }
        }

        return $total;
    }

    public function terimaLama(SaveCashDraft $saver, PostDocument $poster): void
    {
        $this->authorize('perform-admin-mutation');
        $this->validate([
            'lamaTanggal' => ['required', 'date'],
            'lamaKasId' => ['required', 'integer', 'exists:akun,id'],
        ]);

        $sisa = app(OpeningItems::class)->outstanding(SaldoRincianJenis::Pinjaman)->keyBy(fn ($o) => $o['rincian']->id);
        $ids = array_unique(array_merge(array_keys($this->lamaPokok), array_keys($this->lamaBunga), array_keys($this->lamaDenda)));
        $alloc = [];
        $pihak = collect();

        foreach ($ids as $id) {
            $nilai = [];
            foreach (['pokok' => $this->lamaPokok, 'bunga' => $this->lamaBunga, 'denda' => $this->lamaDenda] as $kolom => $isian) {
                $v = trim((string) ($isian[$id] ?? ''));
                if ($v !== '' && (! is_numeric($v) || (float) $v < 0)) {
                    $this->addError("lama.$id", ucfirst($kolom).' harus angka ≥ 0.');

                    return;
                }
                $nilai[$kolom] = $v === '' ? '0' : Decimal::of($v);
            }

            if (! Decimal::isPositive(Decimal::add($nilai['pokok'], Decimal::add($nilai['bunga'], $nilai['denda'])))) {
                continue;
            }
            if (! isset($sisa[$id]) || Decimal::compare($nilai['pokok'], $sisa[$id]['sisa']) > 0) {
                $this->addError("lama.$id", 'Pokok melebihi sisa pinjaman.');

                return;
            }

            $alloc[] = ['opening_rincian_id' => (int) $id, 'nominal' => $nilai['pokok'], 'bunga' => $nilai['bunga'], 'denda' => $nilai['denda']];
            $pihak->push($sisa[$id]['rincian']->pihak_id);
        }

        if ($alloc === []) {
            $this->addError('lama', 'Isi setoran minimal satu nasabah.');

            return;
        }

        try {
            $trx = $saver->handle([
                'tanggal' => $this->lamaTanggal,
                'kategori_kode' => 'setoran_pinjaman_lama',
                'akun_kas_id' => $this->lamaKasId,
                'unit_usaha_id' => UnitUsaha::where('kode_unit', 'UNIT-KREDIT')->value('id'),
                'pihak_id' => $pihak->unique()->count() === 1 ? $pihak->first() : null,
                'keterangan' => 'Setoran pinjaman pedagang lama ('.count($alloc).' nasabah)',
                'allocations' => $alloc,
            ], auth()->user());
            $trx = $poster->handle($trx, auth()->user());
        } catch (DomainException $e) {
            $this->addError('lama', $e->getMessage());

            return;
        }

        $this->reset('lamaPokok', 'lamaBunga', 'lamaDenda');
        session()->flash('status', "Setoran pinjaman lama diposting — No. {$trx->nomor_transaksi} (".count($alloc).' nasabah).');
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    /** Pilih angsuran untuk dibayar; prefill sisa tagihan per komponen. */
    public function startPay(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $a = Angsuran::findOrFail($id);
        $this->payAngsuranId = $id;
        $this->payPokok = Decimal::sub((string) $a->pokok_tagihan, (string) $a->pokok_dibayar);
        $this->payBunga = Decimal::sub((string) $a->bunga_tagihan, (string) $a->bunga_dibayar);
        $this->payDenda = Decimal::sub((string) $a->denda_tagihan, (string) $a->denda_dibayar);
        $this->payTanggal = now()->toDateString();
    }

    public function pay(SaveInstallmentPayment $save, PostingService $posting): void
    {
        $this->authorize('perform-admin-mutation');
        $this->validate([
            'payAngsuranId' => ['required', 'integer', 'exists:angsuran,id'],
            'akunKasId' => ['required', 'integer', 'exists:akun,id'],
            'payTanggal' => ['required', 'date'],
            'payPokok' => ['required', 'numeric', 'min:0'],
            'payBunga' => ['required', 'numeric', 'min:0'],
            'payDenda' => ['required', 'numeric', 'min:0'],
        ]);

        if ((float) $this->payPokok + (float) $this->payBunga + (float) $this->payDenda <= 0) {
            $this->addError('pay', 'Nominal pembayaran harus lebih dari nol.');

            return;
        }

        try {
            $draft = $save->handle([
                'tanggal' => $this->payTanggal,
                'akun_kas_id' => $this->akunKasId,
                'allocations' => [[
                    'angsuran_id' => $this->payAngsuranId,
                    'pokok' => $this->payPokok, 'bunga' => $this->payBunga, 'denda' => $this->payDenda,
                ]],
            ], auth()->user());

            $posting->post($draft, 1, auth()->user(), 'pay:'.$draft->id, 'h');

            $this->reset('payAngsuranId');
            $this->payPokok = $this->payBunga = $this->payDenda = '0';
            session()->flash('status', 'Pembayaran angsuran diposting.');
        } catch (DomainException $e) {
            $this->addError('pay', $e->getMessage());
        }
    }

    public function render()
    {
        $angsuran = Angsuran::query()
            ->with(['pinjaman.peminjam'])
            ->where('status', '!=', 'lunas')
            ->whereHas('pinjaman', fn ($q) => $q->where('status', 'aktif')
                ->when($this->q !== '', fn ($w) => $w->where(fn ($s) => $s
                    ->where('nomor_pinjaman', 'like', "%{$this->q}%")
                    ->orWhereHas('peminjam', fn ($p) => $p->where('nama', 'like', "%{$this->q}%")))))
            ->orderBy('tanggal_jatuh_tempo')
            ->paginate(15);

        return view('livewire.lending.payments', [
            'angsuran' => $angsuran,
            'pinjamanLama' => $this->pinjamanLama(),
            'akunKas' => Akun::where('is_cash', true)->where('is_active', true)->orderBy('kode_akun')->get(),
            'today' => now()->toDateString(),
        ]);
    }
}
