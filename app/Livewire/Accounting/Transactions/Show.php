<?php

namespace App\Livewire\Accounting\Transactions;

use App\Domain\Accounting\PostingService;
use App\Domain\Accounting\ReverseTransaction;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiJenis;
use App\Models\AlokasiPelunasan;
use App\Models\MutasiStok;
use App\Models\Transaksi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use AuthorizesRequests;

    public Transaksi $transaksi;

    public bool $confirmingReverse = false;

    public string $reverseReason = '';

    public function mount(Transaksi $transaksi): void
    {
        $this->transaksi = $transaksi;
    }

    public function post(PostingService $service): void
    {
        $this->authorize('perform-admin-mutation');

        $trx = Transaksi::with(['details', 'barang'])->findOrFail($this->transaksi->id);
        $payload = [
            'id' => $trx->id,
            'version' => $trx->version,
            'barang' => $trx->barang->map(fn ($b) => [$b->produk_id, (string) $b->kuantitas, (string) $b->harga, (string) $b->diskon, (string) $b->pajak])->all(),
            'details' => $trx->details->map(fn ($d) => [$d->akun_id, (string) $d->debit, (string) $d->kredit])->all(),
            'alokasi' => AlokasiPelunasan::where('pembayaran_transaksi_id', $trx->id)
                ->get()->map(fn ($a) => [$a->target_key, (string) $a->nominal])->all(),
        ];
        $hash = hash('sha256', (string) json_encode($payload));
        $key = 'post:'.$trx->id.':'.$trx->version;

        try {
            $service->post($trx, $trx->version, auth()->user(), $key, $hash);
            session()->flash('status', 'Transaksi diposting.');
            $this->transaksi->refresh();
        } catch (DomainException $e) {
            $this->addError('post', $e->getMessage());
        }
    }

    public function reverse(ReverseTransaction $service): void
    {
        $this->authorize('perform-admin-mutation');
        $this->validate(['reverseReason' => ['required', 'string', 'min:3']]);

        $orig = Transaksi::findOrFail($this->transaksi->id);
        $key = 'reverse:'.$orig->id;
        $hash = hash('sha256', $key.$this->reverseReason);

        try {
            $service->reverse($orig, $orig->tanggal->toDateString(), $this->reverseReason, auth()->user(), $key, $hash);
            $this->reset('confirmingReverse', 'reverseReason');
            session()->flash('status', 'Transaksi dibalik.');
            $this->transaksi->refresh();
        } catch (DomainException $e) {
            $this->addError('reverse', $e->getMessage());
        }
    }

    public function render()
    {
        $trx = $this->transaksi->load(['periode', 'unitUsaha', 'pihak', 'details.akun', 'barang.produk']);

        return view('livewire.accounting.transactions.show', [
            'title' => $trx->nomor_transaksi,
            'trx' => $trx,
            'mutasi' => MutasiStok::with('produk')->where('transaksi_id', $trx->id)->orderBy('urutan')->get(),
            // Reverse lewat halaman ini hanya untuk jurnal penyesuaian; reverse
            // dagang/kas (membalik stok/alokasi) menyusul.
            'canReverse' => $trx->jenis_transaksi === TransaksiJenis::Penyesuaian,
        ]);
    }
}
