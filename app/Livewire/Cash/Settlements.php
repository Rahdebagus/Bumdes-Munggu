<?php

namespace App\Livewire\Cash;

use App\Domain\Accounting\PostDocument;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\Cash\SettlementQuery;
use App\Domain\OpeningBalance\OpeningItems;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Enums\SaldoRincianJenis;
use App\Models\Akun;
use App\Models\Pihak;
use App\Models\Transaksi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pelunasan Hutang Usaha (bayar borongan beberapa nota pembelian kredit
 * sekaligus, mis. tiap tanggal 10/20/30) dan Pelunasan Piutang Usaha.
 * Satu dokumen kas kategori pelunasan_utang/pelunasan_piutang dengan alokasi
 * per nota: D Utang Usaha — K Kas/Bank (atau D Kas/Bank — K Piutang Usaha).
 * Nota lama dari saldo awal (rincian cutover) ikut ditampilkan; baris dikunci
 * dengan "I<id>" (invoice) atau "O<id>" (rincian saldo awal).
 */
#[Layout('components.layouts.app')]
class Settlements extends Component
{
    use AuthorizesRequests;

    public string $mode = 'hutang'; // hutang | piutang

    #[Url]
    public ?int $pihakId = null;

    public string $tanggal = '';

    public ?int $akunKasId = null;

    public string $keterangan = '';

    /** @var array<string, bool> kunci baris (I<id>/O<id>) => dipilih */
    public array $pilih = [];

    /** @var array<string, string> kunci baris => nominal bayar */
    public array $bayar = [];

    public function mount(string $mode = 'hutang'): void
    {
        abort_unless(in_array($mode, ['hutang', 'piutang'], true), 404);
        $this->mode = $mode;
        $this->tanggal = now()->toDateString();
        $this->akunKasId = Akun::where('system_key', 'cash')->where('is_active', true)->value('id');
    }

    public function updatedPihakId(): void
    {
        $this->reset('pilih', 'bayar');
    }

    public function toggleAll(bool $on): void
    {
        foreach ($this->outstanding() as $row) {
            $this->pilih[$row['key']] = $on;
            if ($on && ! isset($this->bayar[$row['key']])) {
                $this->bayar[$row['key']] = (string) (float) $row['sisa'];
            }
        }
    }

    public function updatedPilih($value, $key): void
    {
        if ($value && ! isset($this->bayar[$key])) {
            $row = $this->outstanding()->firstWhere('key', $key);
            $this->bayar[$key] = $row ? (string) (float) $row['sisa'] : '0';
        }
    }

    /**
     * Nota lama (saldo awal) lalu nota kredit terposting yang masih bersisa.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function outstanding(): Collection
    {
        $settlement = app(SettlementQuery::class);

        $opening = app(OpeningItems::class)
            ->outstanding($this->mode === 'hutang' ? SaldoRincianJenis::UtangUsaha : SaldoRincianJenis::PiutangUsaha, pihakId: $this->pihakId)
            ->map(fn ($o) => [
                'key' => 'O'.$o['rincian']->id,
                'opening_rincian_id' => $o['rincian']->id,
                'invoice_transaksi_id' => null,
                'nomor' => $o['rincian']->kode_referensi,
                'url' => null,
                'tanggal' => $o['rincian']->tanggal_asal,
                'jatuh_tempo' => $o['rincian']->jatuh_tempo,
                'pihak_id' => $o['rincian']->pihak_id,
                'pihak' => $o['rincian']->pihak?->nama,
                'uraian' => $o['rincian']->metadata['uraian'] ?? null,
                'total' => (string) $o['rincian']->nominal,
                'sisa' => $o['sisa'],
            ]);

        $invoices = Transaksi::with('pihak')
            ->where('jenis_transaksi', $this->mode === 'hutang' ? 'pembelian' : 'penjualan')
            ->where('metode_pembayaran', 'kredit')
            ->whereNotNull('posted_at')->where('status', 'posted')
            ->when($this->pihakId, fn ($q) => $q->where('pihak_id', $this->pihakId))
            ->orderBy('jatuh_tempo')->orderBy('tanggal')
            ->get();
        $terbayar = $settlement->invoiceAllocatedMany($invoices->pluck('id')->all());

        $invoices = $invoices
            ->map(fn ($t) => [
                'key' => 'I'.$t->id,
                'opening_rincian_id' => null,
                'invoice_transaksi_id' => $t->id,
                'nomor' => $t->nomor_transaksi,
                'url' => route('transactions.show', $t),
                'tanggal' => $t->tanggal,
                'jatuh_tempo' => $t->jatuh_tempo,
                'pihak_id' => $t->pihak_id,
                'pihak' => $t->pihak?->nama,
                'uraian' => null,
                'total' => (string) $t->total,
                'sisa' => Decimal::sub((string) $t->total, $terbayar[$t->id] ?? '0'),
            ])
            ->filter(fn ($r) => Decimal::isPositive($r['sisa']));

        return $opening->concat($invoices)->values();
    }

    public function totalBayar(): string
    {
        $total = '0';
        foreach ($this->pilih as $id => $on) {
            if ($on && is_numeric($this->bayar[$id] ?? null)) {
                $total = Decimal::add($total, $this->bayar[$id]);
            }
        }

        return $total;
    }

    public function save(SaveCashDraft $saver, PostDocument $poster): void
    {
        $this->authorize('perform-admin-mutation');
        $this->validate([
            'tanggal' => ['required', 'date'],
            'akunKasId' => ['required', 'integer', 'exists:akun,id'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $sisa = $this->outstanding()->keyBy('key');
        $alloc = [];
        $pihak = collect();
        foreach ($this->pilih as $key => $on) {
            if (! $on) {
                continue;
            }
            $nominal = $this->bayar[$key] ?? '0';
            if (! is_numeric($nominal) || (float) $nominal <= 0) {
                $this->addError("bayar.$key", 'Nominal harus > 0.');

                return;
            }
            if (! isset($sisa[$key]) || Decimal::compare($nominal, $sisa[$key]['sisa']) > 0) {
                $this->addError("bayar.$key", 'Melebihi sisa nota.');

                return;
            }
            $alloc[] = [
                'invoice_transaksi_id' => $sisa[$key]['invoice_transaksi_id'],
                'opening_rincian_id' => $sisa[$key]['opening_rincian_id'],
                'nominal' => (string) $nominal,
            ];
            $pihak->push($sisa[$key]['pihak_id']);
        }

        if ($alloc === []) {
            $this->addError('pilih', 'Centang minimal satu nota.');

            return;
        }

        $pihak = $pihak->unique();

        try {
            $trx = $saver->handle([
                'tanggal' => $this->tanggal,
                'kategori_kode' => $this->mode === 'hutang' ? 'pelunasan_utang' : 'pelunasan_piutang',
                'akun_kas_id' => $this->akunKasId,
                'pihak_id' => $pihak->count() === 1 ? $pihak->first() : null,
                'keterangan' => $this->keterangan ?: ($this->mode === 'hutang' ? 'Pelunasan hutang usaha' : 'Pelunasan piutang usaha').' ('.count($alloc).' nota)',
                'allocations' => $alloc,
            ], auth()->user());
            $trx = $poster->handle($trx, auth()->user());
        } catch (DomainException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        $this->reset('pilih', 'bayar', 'keterangan');
        $this->dispatch('toast', message: "Pelunasan tersimpan & diposting — No. {$trx->nomor_transaksi} (".count($alloc).' nota).');
    }

    public function render()
    {
        $label = $this->mode === 'hutang' ? 'Pelunasan Hutang Usaha' : 'Pelunasan Piutang Usaha';

        return view('livewire.cash.settlements', [
            'title' => $label,
            'label' => $label,
            'rows' => $this->outstanding(),
            'pihakList' => Pihak::where('jenis_pihak', $this->mode === 'hutang' ? 'pemasok' : 'pelanggan')->orderBy('nama')->get(),
            'akunKas' => Akun::where('is_cash', true)->where('is_active', true)->orderBy('kode_akun')->get(),
        ])->title($label);
    }
}
