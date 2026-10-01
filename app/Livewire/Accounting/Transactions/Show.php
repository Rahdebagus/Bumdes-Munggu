<?php

namespace App\Livewire\Accounting\Transactions;

use App\Domain\Accounting\PostingService;
use App\Domain\Accounting\ReverseTransaction;
use App\Domain\Evidence\UploadEvidence;
use App\Domain\Evidence\VerifyEvidence;
use App\Domain\Shared\DomainException;
use App\Models\AlokasiPelunasan;
use App\Models\BuktiTransaksi;
use App\Models\MutasiStok;
use App\Models\Transaksi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public Transaksi $transaksi;

    public bool $confirmingReverse = false;

    public string $reverseReason = '';

    public $buktiFile;

    public string $buktiJenis = 'nota';

    public ?int $rejectingBuktiId = null;

    public string $rejectReason = '';

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

    public function uploadBukti(UploadEvidence $action): void
    {
        $this->authorize('perform-admin-mutation');
        $this->validate([
            'buktiFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'buktiJenis' => ['required', 'in:nota,kuitansi,transfer,kas_masuk,kas_keluar'],
        ]);

        $action->handle($this->transaksi, $this->buktiFile, auth()->user(), $this->buktiJenis);

        $this->reset('buktiFile');
        session()->flash('status', 'Bukti diunggah (menunggu verifikasi).');
    }

    public function verifyBukti(int $id, VerifyEvidence $action): void
    {
        $bukti = BuktiTransaksi::where('transaksi_id', $this->transaksi->id)->findOrFail($id);
        $this->authorize('verify-evidence', $bukti);
        $action->verify($bukti, auth()->user());
        session()->flash('status', 'Bukti diverifikasi.');
    }

    public function rejectBukti(VerifyEvidence $action): void
    {
        $bukti = BuktiTransaksi::where('transaksi_id', $this->transaksi->id)->findOrFail($this->rejectingBuktiId);
        $this->authorize('verify-evidence', $bukti);
        $this->validate(['rejectReason' => ['required', 'string', 'min:3']]);
        $action->reject($bukti, auth()->user(), $this->rejectReason);
        $this->reset('rejectingBuktiId', 'rejectReason');
        session()->flash('status', 'Bukti ditolak.');
    }

    public function render()
    {
        $trx = $this->transaksi->load(['periode', 'unitUsaha', 'pihak', 'details.akun', 'barang.produk', 'bukti.uploadedBy', 'bukti.verifiedBy']);

        return view('livewire.accounting.transactions.show', [
            'title' => $trx->nomor_transaksi,
            'trx' => $trx,
            'mutasi' => MutasiStok::with('produk')->where('transaksi_id', $trx->id)->orderBy('urutan')->get(),
            // Reverse mendukung semua jenis (jurnal, dagang, kas); dependensi
            // (mutasi stok terbaru, pelunasan dulu) ditegakkan domain.
            'canReverse' => true,
        ]);
    }
}
