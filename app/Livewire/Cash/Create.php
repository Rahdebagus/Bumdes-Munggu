<?php

namespace App\Livewire\Cash;

use App\Domain\Cash\SaveCashDraft;
use App\Domain\Cash\SettlementQuery;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Models\Akun;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Kas')]
class Create extends Component
{
    use AuthorizesRequests;

    public string $tanggal = '';

    public string $kategori_kode = 'jasa';

    public ?int $unit_usaha_id = null;

    public ?int $akun_kas_id = null;

    public ?int $akun_lawan_id = null;

    public ?string $keterangan = null;

    public string $nominal = '0';

    /** @var array<int, array{invoice_transaksi_id: ?int, nominal: string}> */
    public array $allocations = [];

    private const PELUNASAN = ['pelunasan_piutang', 'pelunasan_utang'];

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
        $this->allocations = [['invoice_transaksi_id' => null, 'nominal' => '0']];
    }

    public function isPelunasan(): bool
    {
        return in_array($this->kategori_kode, self::PELUNASAN, true);
    }

    public function addAllocation(): void
    {
        $this->allocations[] = ['invoice_transaksi_id' => null, 'nominal' => '0'];
    }

    public function removeAllocation(int $i): void
    {
        unset($this->allocations[$i]);
        $this->allocations = array_values($this->allocations);
    }

    public function save(SaveCashDraft $action)
    {
        $this->authorize('perform-admin-mutation');

        $rules = [
            'tanggal' => ['required', 'date'],
            'kategori_kode' => ['required', 'string'],
            'unit_usaha_id' => ['nullable', 'integer', 'exists:unit_usaha,id'],
            'akun_kas_id' => ['required', 'integer', 'exists:akun,id'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->isPelunasan()) {
            $rules['allocations'] = ['required', 'array', 'min:1'];
            $rules['allocations.*.invoice_transaksi_id'] = ['required', 'integer', 'exists:transaksi,id'];
            $rules['allocations.*.nominal'] = ['required', 'numeric', 'gt:0'];
        } else {
            $rules['akun_lawan_id'] = ['required', 'integer', 'exists:akun,id'];
            $rules['nominal'] = ['required', 'numeric', 'gt:0'];
        }

        $data = $this->validate($rules);

        $payload = [
            'tanggal' => $data['tanggal'],
            'kategori_kode' => $data['kategori_kode'],
            'unit_usaha_id' => $data['unit_usaha_id'] ?? null,
            'akun_kas_id' => $data['akun_kas_id'],
            'keterangan' => $data['keterangan'] ?? null,
        ];

        if ($this->isPelunasan()) {
            $payload['allocations'] = array_map(fn ($a) => [
                'invoice_transaksi_id' => (int) $a['invoice_transaksi_id'],
                'nominal' => $a['nominal'],
            ], $data['allocations']);
        } else {
            $payload['akun_lawan_id'] = $data['akun_lawan_id'];
            $payload['nominal'] = $data['nominal'];
        }

        try {
            $trx = $action->handle($payload, auth()->user());
        } catch (DomainException $e) {
            $this->addError('form', $e->getMessage());

            return null;
        }

        session()->flash('status', 'Draft kas dibuat. Posting dari halaman detail.');

        return $this->redirectRoute('transactions.show', $trx, navigate: true);
    }

    public function render()
    {
        $categories = config('accounting.categories');
        $kategori = $categories[$this->kategori_kode] ?? null;

        $lawanOptions = $kategori
            ? Akun::whereIn('system_key', $kategori['lawan'])->where('is_active', true)->orderBy('kode_akun')->get()
            : collect();

        // Invoice outstanding untuk pelunasan.
        $outstanding = collect();
        if ($this->isPelunasan()) {
            $jenisInvoice = $this->kategori_kode === 'pelunasan_piutang' ? 'penjualan' : 'pembelian';
            $settlement = app(SettlementQuery::class);
            $outstanding = Transaksi::where('jenis_transaksi', $jenisInvoice)
                ->where('metode_pembayaran', 'kredit')
                ->whereNotNull('posted_at')->where('status', 'posted')
                ->get()
                ->map(fn ($t) => (object) ['trx' => $t, 'sisa' => $settlement->invoiceOutstanding($t->id)])
                ->filter(fn ($row) => Decimal::isPositive($row->sisa));
        }

        return view('livewire.cash.create', [
            'categories' => $categories,
            'isPelunasan' => $this->isPelunasan(),
            'lawanOptions' => $lawanOptions,
            'units' => UnitUsaha::where('status', 'aktif')->orderBy('nama_unit')->get(),
            'akunKas' => Akun::where('is_cash', true)->where('is_active', true)->orderBy('kode_akun')->get(),
            'outstanding' => $outstanding,
        ]);
    }
}
