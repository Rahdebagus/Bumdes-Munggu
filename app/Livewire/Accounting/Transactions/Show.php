<?php

namespace App\Livewire\Accounting\Transactions;

use App\Domain\Accounting\DocumentActions;
use App\Domain\Accounting\PostDocument;
use App\Domain\Accounting\ReverseTransaction;
use App\Domain\Evidence\UploadEvidence;
use App\Domain\Evidence\VerifyEvidence;
use App\Domain\Shared\DomainException;
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

    public function post(PostDocument $poster): void
    {
        $this->authorize('perform-admin-mutation');

        try {
            $poster->handle($this->transaksi, auth()->user());
            $this->dispatch('toast', message: "Transaksi {$this->transaksi->nomor_transaksi} diposting — jurnal terbentuk.");
            $this->transaksi->refresh();
        } catch (DomainException $e) {
            $this->addError('post', $e->getMessage());
        }
    }

    public function deleteDraft(DocumentActions $actions)
    {
        $this->authorize('perform-admin-mutation');

        try {
            $nomor = $this->transaksi->nomor_transaksi;
            $actions->deleteDraft($this->transaksi, auth()->user());
        } catch (DomainException $e) {
            $this->addError('post', $e->getMessage());

            return null;
        }

        session()->flash('status', "Draft {$nomor} dihapus.");

        return $this->redirectRoute('transactions.index', navigate: true);
    }

    /** Batal Posting: jurnal pembalik + salinan draft yang langsung dibuka untuk diedit. */
    public function cancelPosting(DocumentActions $actions)
    {
        $this->authorize('perform-admin-mutation');
        $this->validate(['reverseReason' => ['required', 'string', 'min:3']]);

        try {
            $draft = $actions->cancelPosting($this->transaksi, auth()->user(), $this->reverseReason);
        } catch (DomainException $e) {
            $this->addError('reverse', $e->getMessage());

            return null;
        }

        session()->flash('status', "Posting {$this->transaksi->nomor_transaksi} dibatalkan (jurnal pembalik dibuat). Draft koreksi {$draft->nomor_transaksi} siap diedit.");

        return $this->redirect(DocumentActions::editUrl($draft), navigate: true);
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
            $this->dispatch('toast', message: 'Transaksi dibatalkan — jurnal pembalik dibuat.');
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
            'isFormDocument' => DocumentActions::isFormDocument($trx),
            'editUrl' => DocumentActions::editUrl($trx),
        ])->title('Detail Transaksi');
    }
}
