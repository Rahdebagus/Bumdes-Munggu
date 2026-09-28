<?php

namespace App\Livewire\Accounting;

use App\Domain\Accounting\PostingService;
use App\Domain\Accounting\ReverseTransaction;
use App\Domain\Accounting\SaveManualJournal;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiJenis;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Jurnal Penyesuaian')]
class ManualJournal extends Component
{
    use AuthorizesRequests;

    public ?int $periodeId = null;

    public string $tanggal = '';

    public ?string $keterangan = null;

    /** @var array<int, array{akun_id: ?int, debit: string, kredit: string}> */
    public array $lines = [];

    public ?int $reversingId = null;

    public string $reverseReason = '';

    public function mount(): void
    {
        $this->lines = [
            ['akun_id' => null, 'debit' => '0', 'kredit' => '0'],
            ['akun_id' => null, 'debit' => '0', 'kredit' => '0'],
        ];
        $this->tanggal = now()->toDateString();
    }

    public function addLine(): void
    {
        $this->lines[] = ['akun_id' => null, 'debit' => '0', 'kredit' => '0'];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    public function saveDraft(SaveManualJournal $action): void
    {
        $this->authorize('perform-admin-mutation');

        $data = $this->validate([
            'tanggal' => ['required', 'date'],
            'periodeId' => ['required', 'integer', 'exists:periode_akuntansi,id'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.akun_id' => ['required', 'integer', 'exists:akun,id'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.kredit' => ['required', 'numeric', 'min:0'],
        ]);

        $action->handle([
            'tanggal' => $data['tanggal'],
            'periode_id' => $data['periodeId'],
            'keterangan' => $data['keterangan'],
            'lines' => array_map(fn ($l) => [
                'akun_id' => (int) $l['akun_id'],
                'debit' => $l['debit'],
                'kredit' => $l['kredit'],
            ], $data['lines']),
        ], auth()->user());

        $this->reset('keterangan');
        $this->lines = [
            ['akun_id' => null, 'debit' => '0', 'kredit' => '0'],
            ['akun_id' => null, 'debit' => '0', 'kredit' => '0'],
        ];
        session()->flash('status', 'Draft jurnal penyesuaian dibuat.');
    }

    public function post(int $id, PostingService $service): void
    {
        $this->authorize('perform-admin-mutation');

        $trx = Transaksi::with('details')
            ->where('jenis_transaksi', TransaksiJenis::Penyesuaian->value)
            ->findOrFail($id);

        $lines = $trx->details->map(fn ($d) => [$d->akun_id, (string) $d->debit, (string) $d->kredit])->all();
        $hash = hash('sha256', (string) json_encode($lines));
        $key = 'post:'.$trx->id.':'.$trx->version;

        try {
            $service->post($trx, $trx->version, auth()->user(), $key, $hash);
            session()->flash('status', 'Jurnal diposting.');
        } catch (DomainException $e) {
            $this->addError('post', $e->getMessage());
        }
    }

    public function reverse(ReverseTransaction $service): void
    {
        $this->authorize('perform-admin-mutation');

        $this->validate(['reverseReason' => ['required', 'string', 'min:3']]);

        $orig = Transaksi::findOrFail($this->reversingId);
        $key = 'reverse:'.$orig->id;
        $hash = hash('sha256', $key.$this->reverseReason);

        try {
            $service->reverse($orig, $orig->tanggal->toDateString(), $this->reverseReason, auth()->user(), $key, $hash);
            $this->reset('reversingId', 'reverseReason');
            session()->flash('status', 'Transaksi dibalik.');
        } catch (DomainException $e) {
            $this->addError('reverse', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.accounting.manual-journal', [
            'akun' => Akun::where('is_active', true)->orderBy('kode_akun')->get(),
            'periods' => PeriodeAkuntansi::orderByDesc('tahun')->orderByDesc('bulan')->get(),
            'journals' => Transaksi::with('details')
                ->where('jenis_transaksi', TransaksiJenis::Penyesuaian->value)
                ->orderByDesc('id')
                ->paginate(10),
        ]);
    }
}
