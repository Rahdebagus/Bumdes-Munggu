<?php

namespace App\Livewire\Trading;

use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Domain\Trading\SaveTradingDraft;
use App\Enums\TransaksiJenis;
use App\Models\Akun;
use App\Models\Pihak;
use App\Models\Produk;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Create extends Component
{
    use AuthorizesRequests;

    public string $jenis = 'penjualan'; // penjualan | pembelian

    public string $tanggal = '';

    public ?int $unit_usaha_id = null;

    public ?int $pihak_id = null;

    public string $metode_pembayaran = 'tunai';

    public ?int $akun_kas_id = null;

    public ?string $jatuh_tempo = null;

    public ?string $keterangan = null;

    /** @var array<int, array{produk_id: ?int, kuantitas: string, harga: string, diskon: string, pajak: string}> */
    public array $items = [];

    public function mount(string $jenis = 'penjualan'): void
    {
        abort_unless(in_array($jenis, ['penjualan', 'pembelian'], true), 404);
        $this->jenis = $jenis;
        $this->tanggal = now()->toDateString();
        $this->items = [['produk_id' => null, 'kuantitas' => '1', 'harga' => '0', 'diskon' => '0', 'pajak' => '0']];
    }

    public function addItem(): void
    {
        $this->items[] = ['produk_id' => null, 'kuantitas' => '1', 'harga' => '0', 'diskon' => '0', 'pajak' => '0'];
    }

    public function removeItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
    }

    public function getTotalProperty(): string
    {
        $total = '0';
        foreach ($this->items as $it) {
            if (! is_numeric($it['kuantitas']) || ! is_numeric($it['harga'])) {
                continue;
            }
            $net = Decimal::sub(Decimal::mul($it['kuantitas'], $it['harga']), $it['diskon'] ?: 0);
            $total = Decimal::add($total, Decimal::add($net, $it['pajak'] ?: 0));
        }

        return $total;
    }

    public function save(SaveTradingDraft $action)
    {
        $this->authorize('perform-admin-mutation');

        $data = $this->validate([
            'tanggal' => ['required', 'date'],
            'unit_usaha_id' => ['required', 'integer', 'exists:unit_usaha,id'],
            'pihak_id' => ['nullable', 'integer', 'exists:pihak,id'],
            'metode_pembayaran' => ['required', 'in:tunai,kredit'],
            'akun_kas_id' => ['nullable', 'integer', 'exists:akun,id', 'required_if:metode_pembayaran,tunai'],
            'jatuh_tempo' => ['nullable', 'date', 'required_if:metode_pembayaran,kredit'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'integer', 'exists:produk,id'],
            'items.*.kuantitas' => ['required', 'numeric', 'gt:0'],
            'items.*.harga' => ['required', 'numeric', 'min:0'],
            'items.*.diskon' => ['nullable', 'numeric', 'min:0'],
            'items.*.pajak' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Diskon tidak boleh melebihi (qty × harga) → subtotal baris negatif.
        foreach ($data['items'] as $idx => $it) {
            $net = Decimal::sub(Decimal::mul($it['kuantitas'], $it['harga']), $it['diskon'] ?: 0);
            if (Decimal::compare($net, 0) < 0) {
                $this->addError("items.$idx.diskon", 'Diskon melebihi (qty × harga); subtotal baris tidak boleh negatif.');

                return null;
            }
        }

        try {
            $trx = $action->handle([
                'tanggal' => $data['tanggal'],
                'unit_usaha_id' => $data['unit_usaha_id'],
                'pihak_id' => $data['pihak_id'] ?? null,
                'metode_pembayaran' => $data['metode_pembayaran'],
                'akun_kas_id' => $data['akun_kas_id'] ?? null,
                'jatuh_tempo' => $data['jatuh_tempo'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'items' => $data['items'],
            ], TransaksiJenis::from($this->jenis), auth()->user());
        } catch (DomainException $e) {
            $this->addError('form', $e->getMessage());

            return null;
        }

        session()->flash('status', 'Draft '.$this->jenis.' dibuat. Unggah bukti lalu posting.');

        return $this->redirectRoute('transactions.show', $trx, navigate: true);
    }

    public function render()
    {
        return view('livewire.trading.create', [
            'title' => 'Transaksi '.ucfirst($this->jenis),
            'units' => UnitUsaha::where('status', 'aktif')->orderBy('nama_unit')->get(),
            'pihak' => Pihak::where('status', 'aktif')
                ->where('jenis_pihak', $this->jenis === 'penjualan' ? 'pelanggan' : 'pemasok')
                ->orderBy('nama')->get(),
            'produk' => Produk::where('status', 'aktif')->orderBy('nama_produk')->get(),
            'akunKas' => Akun::where('is_cash', true)->where('is_active', true)->orderBy('kode_akun')->get(),
        ]);
    }
}
